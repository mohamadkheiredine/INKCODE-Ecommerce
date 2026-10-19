#!/usr/bin/env node
// Prepares this git worktree for slot N (1–4), so several worktrees can develop, test and run
// e2e at the same time: each slot has its own databases and dev-server ports (CLAUDE.md,
// "Parallel worktrees"). Slot 0 is the main checkout and needs none of this.
//
//   node scripts/use-slot.mjs <1-4> [--fresh] [--force]
//
//   --fresh  rebuild the slot's dev database (migrate:fresh) instead of migrating it
//   --force  continue even if another worktree is already configured for this slot

import { execFileSync, spawnSync } from 'node:child_process'
import { randomBytes } from 'node:crypto'
import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import net from 'node:net'
import path from 'node:path'

const CONTAINER = 'inkcode-mysql'
const root = path.resolve(import.meta.dirname, '..')
const backend = path.join(root, 'backend')
const frontend = path.join(root, 'frontend')

const args = process.argv.slice(2)
const slot = Number(args.find((a) => !a.startsWith('--')))
const fresh = args.includes('--fresh')
const force = args.includes('--force')

if (!Number.isInteger(slot) || slot < 1 || slot > 4) {
  fail(
    'Usage: node scripts/use-slot.mjs <1-4> [--fresh] [--force]\n' +
      'Slot 0 is the main checkout; set it up with `composer setup` as usual.',
  )
}

const names = {
  devDb: `inkcode_w${slot}`,
  testDb: `inkcode_testing_w${slot}`,
  apiPort: 8000 + slot,
  spaPort: 5170 + slot,
}
const apiUrl = `http://localhost:${names.apiPort}`
const spaUrl = `http://localhost:${names.spaPort}`

step(`Preparing this worktree for slot ${slot}`)
refuseIfSlotTaken()
await warnIfPortsBusy()

step(`Creating ${names.devDb} and ${names.testDb} in ${CONTAINER}`)
const db = createDatabases()

step('Writing backend/.env and frontend/.env.local')
writeEnv(path.join(backend, '.env'), path.join(backend, '.env.example'), {
  APP_URL: apiUrl,
  SERVER_PORT: String(names.apiPort),
  DB_DATABASE: names.devDb,
  DB_TEST_DATABASE: names.testDb,
  DB_USERNAME: db.user,
  DB_PASSWORD: db.password,
  FRONTEND_URL: spaUrl,
  SANCTUM_STATEFUL_DOMAINS: `localhost:${names.spaPort},127.0.0.1:${names.spaPort}`,
  // Browsers share cookies across ports, so each slot keeps its own session cookie.
  SESSION_COOKIE: `inkcode_w${slot}_session`,
  DEMO_MODE: 'true',
})
writeEnv(path.join(frontend, '.env.local'), path.join(frontend, '.env.example'), {
  SPA_PORT: String(names.spaPort),
  API_PORT: String(names.apiPort),
})

if (!existsSync(path.join(backend, 'vendor'))) {
  step('Installing backend dependencies (composer install)')
  run('composer', ['install', '--no-interaction', '--no-progress'], backend)
}
if (!existsSync(path.join(frontend, 'node_modules'))) {
  step('Installing frontend dependencies (npm ci)')
  run('npm', ['ci', '--no-audit', '--no-fund'], frontend)
}

step(`${fresh ? 'Rebuilding' : 'Migrating'} and seeding ${names.devDb} (demo mode)`)
run('php', ['artisan', 'config:clear'], backend)
run('php', ['artisan', fresh ? 'migrate:fresh' : 'migrate', '--seed', '--force'], backend)

console.log(`
Slot ${slot} is ready.

  SPA        ${spaUrl}
  API        ${apiUrl}   (API docs: ${apiUrl}/docs/api)
  Dev DB     ${names.devDb}
  Test DB    ${names.testDb}

Start the servers (two terminals):
  cd backend  && composer dev      # php artisan serve on port ${names.apiPort}
  cd frontend && npm run dev       # Vite on port ${names.spaPort}

Tests:
  cd backend  && composer check    # Pest runs against ${names.testDb}
  cd frontend && npm run check
  cd frontend && npx playwright test   # starts or reuses this slot's servers only
`)

// Another worktree whose .env already points at this slot's database would share it.
function refuseIfSlotTaken() {
  const list = execFileSync('git', ['worktree', 'list', '--porcelain'], {
    cwd: root,
    encoding: 'utf8',
  })
  const others = list
    .split('\n')
    .filter((line) => line.startsWith('worktree '))
    .map((line) => path.resolve(line.slice('worktree '.length).trim()))
    .filter((dir) => dir.toLowerCase() !== root.toLowerCase())
  const holders = others.filter((dir) => {
    const env = path.join(dir, 'backend', '.env')
    return existsSync(env) && readEnvValue(readFileSync(env, 'utf8'), 'DB_DATABASE') === names.devDb
  })
  if (holders.length > 0 && !force) {
    fail(
      `Slot ${slot} is already configured in:\n  ${holders.join('\n  ')}\n` +
        'Pick a free slot, or pass --force if that worktree is no longer in use.',
    )
  }
}

async function warnIfPortsBusy() {
  for (const port of [names.apiPort, names.spaPort]) {
    if (await isListening(port)) {
      console.warn(
        `  ! Something already listens on port ${port}. If it isn't this worktree's own server, ` +
          'another session is using this slot.',
      )
    }
  }
}

function isListening(port) {
  return new Promise((resolve) => {
    const socket = net.connect({ port, host: 'localhost' })
    const done = (listening) => {
      socket.destroy()
      resolve(listening)
    }
    socket.setTimeout(500)
    socket.once('connect', () => done(true))
    socket.once('timeout', () => done(false))
    socket.once('error', () => done(false))
  })
}

// Creates the slot's databases as root, using the container's own credentials, and grants
// the app's user (MYSQL_USER from docker-compose.yml) access, like docker/mysql/init does.
function createDatabases() {
  const status = docker(['inspect', '-f', '{{.State.Running}}', CONTAINER], 'ignore').trim()
  if (status !== 'true') {
    fail(`The ${CONTAINER} container isn't running. Start it with: docker compose up -d mysql`)
  }
  const user = docker(['exec', CONTAINER, 'printenv', 'MYSQL_USER']).trim()
  const password = docker(['exec', CONTAINER, 'printenv', 'MYSQL_PASSWORD']).trim()
  if (!/^\w+$/.test(user)) fail(`Unexpected MYSQL_USER in ${CONTAINER}: ${user}`)

  const sql = [names.devDb, names.testDb]
    .map(
      (name) =>
        `CREATE DATABASE IF NOT EXISTS \`${name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n` +
        `GRANT ALL PRIVILEGES ON \`${name}\`.* TO '${user}'@'%';`,
    )
    .join('\n')

  // The mysql client inside the container may need a few seconds after a restart.
  for (let attempt = 1; ; attempt++) {
    try {
      execFileSync(
        'docker',
        ['exec', '-i', CONTAINER, 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot'],
        {
          input: `${sql}\nFLUSH PRIVILEGES;\n`,
          stdio: ['pipe', 'inherit', 'inherit'],
        },
      )
      break
    } catch (error) {
      if (attempt === 10) fail(`Could not create the databases: ${error.message}`)
      Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 2000)
    }
  }
  return { user, password }
}

// Writes `file`, starting from its current content (or `example`), with `values` set.
function writeEnv(file, example, values) {
  let text = readFileSync(existsSync(file) ? file : example, 'utf8')
  for (const [key, value] of Object.entries(values)) {
    const line = `${key}=${value}`
    const pattern = new RegExp(`^(?:#\\s*)?${key}=.*$`, 'm')
    text = pattern.test(text) ? text.replace(pattern, line) : `${text.trimEnd()}\n${line}\n`
  }
  if (path.basename(file) === '.env' && !readEnvValue(text, 'APP_KEY')) {
    text = text.replace(/^APP_KEY=.*$/m, `APP_KEY=base64:${randomBytes(32).toString('base64')}`)
  }
  writeFileSync(file, text)
}

function readEnvValue(text, key) {
  const match = text.match(new RegExp(`^${key}=(.*)$`, 'm'))
  return match ? match[1].trim().replace(/^"(.*)"$/, '$1') : ''
}

function docker(dockerArgs, stderr = 'inherit') {
  try {
    return execFileSync('docker', dockerArgs, {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', stderr],
    })
  } catch {
    fail(`\`docker ${dockerArgs.join(' ')}\` failed. Is Docker Desktop running?`)
  }
}

// composer and npm are .bat/.cmd shims on Windows, which only start through a shell. The
// arguments are this script's own fixed flags, so joining them into one command is safe.
function run(command, commandArgs, cwd) {
  const result =
    process.platform === 'win32'
      ? spawnSync([command, ...commandArgs].join(' '), { cwd, stdio: 'inherit', shell: true })
      : spawnSync(command, commandArgs, { cwd, stdio: 'inherit' })
  if (result.status !== 0) {
    fail(`\`${command} ${commandArgs.join(' ')}\` failed in ${path.relative(root, cwd) || '.'}.`)
  }
}

function step(message) {
  console.log(`\n> ${message}`)
}

function fail(message) {
  console.error(`\n${message}`)
  process.exit(1)
}
