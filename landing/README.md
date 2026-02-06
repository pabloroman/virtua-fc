# Virtua FC — Landing Page

Lead generation landing page for Virtua FC, hosted on Cloudflare Pages with D1 for form submissions.

## Stack

- **Static HTML + Tailwind CSS** (via CDN)
- **Cloudflare Pages** for hosting
- **Cloudflare Pages Functions** (Workers) for form API
- **Cloudflare D1** (SQLite) for storing waitlist submissions

## Setup

### Prerequisites

- A [Cloudflare account](https://dash.cloudflare.com/sign-up) (free)
- Node.js 18+

### 1. Install dependencies

```bash
cd landing
npm install
```

### 2. Create the D1 database

```bash
npx wrangler d1 create virtua-fc-waitlist
```

Copy the `database_id` from the output and paste it into `wrangler.toml`.

### 3. Initialize the database schema

```bash
npx wrangler d1 execute virtua-fc-waitlist --remote --file=schema.sql
```

### 4. Local development

```bash
npm run dev
```

This starts a local dev server with a local D1 database at `http://localhost:8788`.

### 5. Deploy

```bash
npm run deploy
```

Or connect the repo to Cloudflare Pages via the dashboard:

1. Go to **Cloudflare Dashboard > Pages > Create a project**
2. Connect your GitHub repo
3. Set build output directory to `landing/public`
4. Set root directory to `landing`
5. Add the D1 binding: **Settings > Functions > D1 database bindings** — variable name `DB`, select `virtua-fc-waitlist`

## Exporting waitlist data

```bash
npx wrangler d1 execute virtua-fc-waitlist --remote --command "SELECT * FROM waitlist"
```

Or export as JSON:

```bash
npx wrangler d1 execute virtua-fc-waitlist --remote --command "SELECT * FROM waitlist" --json
```

## Project structure

```
landing/
├── public/
│   └── index.html          # Landing page
├── functions/
│   └── api/
│       └── submit.js       # Form submission handler (Cloudflare Worker)
├── schema.sql              # D1 database schema
├── wrangler.toml           # Cloudflare configuration
├── package.json
└── README.md
```
