# Virtua FC — Landing Page

Lead generation landing page for Virtua FC, hosted on Cloudflare Pages.

The waitlist form posts to the main Laravel app (`POST https://play.virtuafc.com/api/waitlist`,
handled by `App\Http\Actions\JoinWaitlist`), so entries land in the app's own `waitlist` table
and show up under **Admin → Waitlist**. There is no separate datastore here — the page is pure
static hosting.

## Stack

- **Static HTML + Tailwind CSS** (via CDN)
- **Cloudflare Pages** for hosting

## Setup

### Prerequisites

- A [Cloudflare account](https://dash.cloudflare.com/sign-up) (free)
- Node.js 18+

### 1. Install dependencies

```bash
cd landing
npm install
```

### 2. Local development

```bash
npm run dev
```

Serves `public/` at `http://localhost:8788`. Note that the form still posts to the production
API, so submissions from a local run create real waitlist entries.

### 3. Deploy

```bash
npm run deploy
```

Or connect the repo to Cloudflare Pages via the dashboard:

1. Go to **Cloudflare Dashboard > Pages > Create a project**
2. Connect your GitHub repo
3. Set build output directory to `landing/public`
4. Set root directory to `landing`

## Project structure

```
landing/
├── public/
│   ├── index.html          # Landing page (Spanish)
│   ├── index-en.html       # Landing page (English)
│   ├── img/
│   └── screenshots/
├── wrangler.toml           # Cloudflare configuration
├── package.json
└── README.md
```
