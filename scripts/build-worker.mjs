import { createHash } from 'node:crypto'
import { readFile, writeFile } from 'node:fs/promises'

const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'))
const worker = await readFile('resources/js/sw.js', 'utf8')
const buildAssets = Object.values(manifest).flatMap(entry => [entry.file, ...(entry.css || []), ...(entry.assets || [])])
const resources = [...new Set(buildAssets)].sort().map(file => '/build/' + file)
resources.push('/offline', '/manifest.webmanifest', '/icons/icon.svg', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/maskable-512.png')
const shellTemplates = await Promise.all([
    readFile('resources/views/offline.blade.php'),
    readFile('resources/views/layout.blade.php'),
])
const hash = createHash('sha256').update(worker).update(JSON.stringify(resources))
for (const template of shellTemplates) hash.update(template)
const revision = hash.digest('hex').slice(0, 20)
await writeFile('public/sw.js', `const CACHE = 'utilynk-shell-${revision}';\nconst RESOURCES = ${JSON.stringify(resources)};\n${worker}`)
