// Tile Engine - uses simplex-noise.js
// Include simplex-noise.js before this file

const TILE_SIZE = 16;
const CACHE_LIMIT = 200;
const NOISE_SCALE = 0.01;
const FREQUENCY = 2.5;
const CHUNK_SIZE = 8; // 8x8 tiles per chunk

class LRU {
  constructor(max) {
    this.max = max;
    this.map = new Map();
  }

  get(k) {
    if (!this.map.has(k)) return null;
    const v = this.map.get(k);
    this.map.delete(k);
    this.map.set(k, v);
    return v;
  }

  set(k, v) {
    this.map.set(k, v);
    if (this.map.size > this.max) {
      this.map.delete(this.map.keys().next().value);
    }
  }
}

function pseudoRandomFromSeed(seed) {
  // Convertiamo il seed in stringa se necessario e gestiamo il caso undefined
  if (seed === undefined || seed === null) {
    seed = 123456; // Default seed
  }
  let s = seed.toString().split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
  return () => {
    s = Math.sin(s) * 10000;
    return s - Math.floor(s);
  };
}

function createTileEngine(seed, terrainArr) {
  const simplex = createNoise2D(pseudoRandomFromSeed(seed));
  const cache = new LRU(CACHE_LIMIT);

  const ordered = terrainArr?.length ? terrainArr : [
    { color: '#00ff00' },
    { color: '#ff00ff' }
  ];

  function noiseVal(x, y) {
    return (simplex(x * FREQUENCY, y * FREQUENCY) + 1) / 2;
  }

  function colorFor(v) {
    const idx = Math.min(ordered.length - 1, Math.floor(v * ordered.length));
    if (idx < 0 || idx >= 20) throw new Error("Creazione mappa non concessa");
    return ordered[idx]?.color || '#FF0000';
  }

  function getChunk(cx, cy) {
    const key = "chunk_" + cx + "," + cy;
    let bmp = cache.get(key);
    if (bmp) return Promise.resolve(bmp);

    const imgData = new ImageData(CHUNK_SIZE * TILE_SIZE, CHUNK_SIZE * TILE_SIZE);
    const data = imgData.data;

    for (let px = 0; px < CHUNK_SIZE * TILE_SIZE; px++) {
      for (let py = 0; py < CHUNK_SIZE * TILE_SIZE; py++) {
        const tx = cx * CHUNK_SIZE + Math.floor(px / TILE_SIZE);
        const ty = cy * CHUNK_SIZE + Math.floor(py / TILE_SIZE);

        const fx = (tx + (px % TILE_SIZE) / TILE_SIZE) * NOISE_SCALE;
        const fy = (ty + (py % TILE_SIZE) / TILE_SIZE) * NOISE_SCALE;

        const v = noiseVal(fx, fy);
        const hex = colorFor(v);
        const rgb = parseInt(hex.slice(1), 16);
        const off = (py * CHUNK_SIZE * TILE_SIZE * 4) + (px * 4);

        data[off] = (rgb >> 16) & 0xFF;
        data[off + 1] = (rgb >> 8) & 0xFF;
        data[off + 2] = rgb & 0xFF;
        data[off + 3] = 0xFF;
      }
    }

    return createImageBitmap(imgData).then(function(bitmap) {
      cache.set(key, bitmap);
      return bitmap;
    });
  }

  return { TILE_SIZE: TILE_SIZE, CHUNK_SIZE: CHUNK_SIZE, getChunk: getChunk };
}
