// Writes dist/<page>/index.html for each public page with its own title, description and canonical URL.
// Without this every URL serves the homepage's head, so Google sees /privacy, /terms, ... as copies of "/".
// The Vue app still loads on top and takes over as usual.
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const BASE = 'https://japacoach.com'
const DEFAULT_TITLE = 'Japa Coach: AI IELTS practice for Nigerians'

const pages = JSON.parse(readFileSync(join(root, 'src/router/seo.json'), 'utf8'))
const template = readFileSync(join(root, 'dist/index.html'), 'utf8')

const esc = (s) => s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

/** Replace the content of a tag matched by `pattern`, failing loudly if index.html changed shape. */
function swap(html, pattern, value, label) {
  if (!pattern.test(html)) throw new Error(`prerender: ${label} not found in dist/index.html`)
  return html.replace(pattern, `$1${value}$2`)
}

for (const [path, page] of Object.entries(pages)) {
  if (path === '/') continue // dist/index.html already is the homepage
  const title = esc(page.title ? `${page.title} · Japa Coach` : DEFAULT_TITLE)
  const description = esc(page.description)
  const url = BASE + path

  let html = template
  html = swap(html, /(<title>)[^<]*(<\/title>)/, title, 'title')
  html = swap(html, /(<meta name="description" content=")[^"]*(")/, description, 'description')
  html = swap(html, /(<link rel="canonical" href=")[^"]*(")/, url, 'canonical')
  html = swap(html, /(<meta property="og:url" content=")[^"]*(")/, url, 'og:url')
  html = swap(html, /(<meta property="og:title" content=")[^"]*(")/, title, 'og:title')
  html = swap(html, /(<meta property="og:description" content=")[^"]*(")/, description, 'og:description')
  html = swap(html, /(<meta name="twitter:title" content=")[^"]*(")/, title, 'twitter:title')
  html = swap(html, /(<meta name="twitter:description" content=")[^"]*(")/, description, 'twitter:description')

  const out = join(root, 'dist', path.slice(1), 'index.html')
  mkdirSync(dirname(out), { recursive: true })
  writeFileSync(out, html)
  console.log(`prerendered ${path}`)
}
