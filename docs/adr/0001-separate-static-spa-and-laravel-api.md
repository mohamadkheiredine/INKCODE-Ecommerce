# Separate static React SPA and Laravel API in one repository

The assessment requires the React frontend to compile to standalone static files and deploy separately from Laravel (no Next.js, Inertia or server-rendered React). We keep both in one Git repository (`frontend/`, `backend/`) so one commit history covers each feature end to end, while each app builds, tests and deploys independently. The frontend only talks to the database through the versioned `/api/v1` JSON API.
