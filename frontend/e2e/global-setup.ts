import { execFileSync } from 'node:child_process'
import path from 'node:path'

/**
 * Makes sure the Roles and demo accounts exist. The seeders are idempotent, so this
 * never wipes data; it only brings reference data and demo accounts up to date.
 */
export default function globalSetup(): void {
  const backend = path.resolve(import.meta.dirname, '../../backend')
  execFileSync('php', ['artisan', 'migrate', '--force'], { cwd: backend, stdio: 'inherit' })
  execFileSync('php', ['artisan', 'db:seed', '--force'], {
    cwd: backend,
    stdio: 'inherit',
    env: { ...process.env, DEMO_MODE: 'true' },
  })
}
