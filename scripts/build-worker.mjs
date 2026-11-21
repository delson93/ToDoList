import { createHash } from 'node:crypto'
import { readFile, writeFile } from 'node:fs/promises'

const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'))
const worker = await readFile('resources/js/sw.js', 'utf8')
const buildAssets = Object.values(manifest).flatMap(entry => [entry.file, ...(entry.css || []), ...(entry.assets || [])])
const resources = [...new Set(buildAssets)].sort().map(file => '/build/' + file)
resources.push('/offline', '/manifest.webmanifest', '/icons/icon.svg', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/maskable-512.png')
const revision = createHash('sha256').update(worker).update(JSON.stringify(resources)).digest('hex').slice(0, 20)
await writeFile('public/sw.js', `const CACHE = 'utilynk-shell-${revision}';\nconst RESOURCES = ${JSON.stringify(resources)};\n${worker}`)
