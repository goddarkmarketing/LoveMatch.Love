import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import sharp from "sharp";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, "..");
const sourceDir = path.join(rootDir, "assets", "stickers");
const outputDir = path.join(rootDir, "assets", "stickers", "chat");
const manifestPath = path.join(outputDir, "manifest.json");

const GAP_RATIO = 0.018;
const MIN_STICKER_SIZE = 80;
const PADDING = 8;

function isBackground(data, idx) {
  const alpha = data[idx + 3];
  if (alpha < 20) {
    return true;
  }
  const red = data[idx];
  const green = data[idx + 1];
  const blue = data[idx + 2];
  return (red > 240 && green > 240 && blue > 240) || (red < 15 && green < 15 && blue < 15);
}

function findBands(counts, crossSize) {
  const threshold = Math.max(8, Math.floor(crossSize * GAP_RATIO));
  const bands = [];
  let start = null;

  for (let i = 0; i < counts.length; i += 1) {
    const isGap = counts[i] <= threshold;
    if (!isGap && start === null) {
      start = i;
    }
    if (isGap && start !== null) {
      if (i - start >= MIN_STICKER_SIZE) {
        bands.push([start, i]);
      }
      start = null;
    }
  }

  if (start !== null && counts.length - start >= MIN_STICKER_SIZE) {
    bands.push([start, counts.length]);
  }

  return bands;
}

async function detectGrid(imagePath) {
  const { data, info } = await sharp(imagePath)
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  const rowCounts = new Array(info.height).fill(0);
  const colCounts = new Array(info.width).fill(0);

  for (let y = 0; y < info.height; y += 1) {
    for (let x = 0; x < info.width; x += 1) {
      const idx = (y * info.width + x) * info.channels;
      if (!isBackground(data, idx)) {
        rowCounts[y] += 1;
        colCounts[x] += 1;
      }
    }
  }

  const rows = findBands(rowCounts, info.width);
  const cols = findBands(colCounts, info.height);

  return { rows, cols, info };
}

function fallbackGrid(width, height) {
  if (width === 800 && height === 800) {
    return { rows: [[0, 400], [400, 800]], cols: [[0, 400], [400, 800]] };
  }
  if (width === 1254 && height === 1254) {
    const cell = Math.floor(1254 / 3);
    return {
      rows: [[0, cell], [cell, cell * 2], [cell * 2, 1254]],
      cols: [[0, cell], [cell, cell * 2], [cell * 2, 1254]],
    };
  }
  return null;
}

async function trimRegion(inputPath, left, top, width, height, outputPath) {
  const pipeline = sharp(inputPath).extract({ left, top, width, height });
  const trimmed = await pipeline.trim({ threshold: 12 }).toBuffer();
  await sharp(trimmed).png({ compressionLevel: 9 }).toFile(outputPath);
}

async function splitSheet(fileName) {
  const inputPath = path.join(sourceDir, fileName);
  const meta = await sharp(inputPath).metadata();
  const imageWidth = meta.width || 0;
  const imageHeight = meta.height || 0;
  let { rows, cols } = await detectGrid(inputPath);

  if (rows.length < 2 || cols.length < 2) {
    const fallback = fallbackGrid(imageWidth, imageHeight);
    if (fallback) {
      rows = fallback.rows;
      cols = fallback.cols;
    }
  }

  const stickers = [];
  let index = 0;

  for (const [rowStart, rowEnd] of rows) {
    for (const [colStart, colEnd] of cols) {
      const left = Math.max(0, colStart - PADDING);
      const top = Math.max(0, rowStart - PADDING);
      const right = Math.min(imageWidth, colEnd + PADDING);
      const bottom = Math.min(imageHeight, rowEnd + PADDING);
      const width = right - left;
      const height = bottom - top;

      if (width < MIN_STICKER_SIZE || height < MIN_STICKER_SIZE || left + width > imageWidth || top + height > imageHeight) {
        continue;
      }

      index += 1;
      const slug = path.basename(fileName, path.extname(fileName))
        .replace(/[^a-zA-Z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "")
        .toLowerCase();
      const outName = `${slug}-${String(index).padStart(2, "0")}.png`;
      const outPath = path.join(outputDir, outName);

      try {
        await trimRegion(inputPath, left, top, width, height, outPath);
      } catch {
        continue;
      }

      const outMeta = await sharp(outPath).metadata();
      stickers.push({
        id: `${slug}-${String(index).padStart(2, "0")}`,
        file: `assets/stickers/chat/${outName}`,
        width: outMeta.width || 0,
        height: outMeta.height || 0,
        source: fileName,
      });
    }
  }

  return stickers;
}

async function main() {
  if (!fs.existsSync(sourceDir)) {
    throw new Error(`Source folder not found: ${sourceDir}`);
  }

  fs.mkdirSync(outputDir, { recursive: true });

  const files = fs
    .readdirSync(sourceDir, { withFileTypes: true })
    .filter((entry) => entry.isFile() && entry.name.toLowerCase().endsWith(".png"))
    .map((entry) => entry.name)
    .sort();

  const allStickers = [];

  for (const fileName of files) {
    const sheetStickers = await splitSheet(fileName);
    allStickers.push(...sheetStickers);
    console.log(`${fileName}: ${sheetStickers.length} stickers`);
  }

  const manifest = {
    version: 1,
    generated_at: new Date().toISOString(),
    count: allStickers.length,
    stickers: allStickers.map((item, order) => ({
      id: item.id,
      file: item.file,
      label: `สติกเกอร์ ${order + 1}`,
      width: item.width,
      height: item.height,
      source: item.source,
    })),
  };

  fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2), "utf8");
  console.log(`Done. ${allStickers.length} stickers -> ${outputDir}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
