#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');

const themesDir = path.resolve(__dirname, '../themes');
if (!fs.existsSync(themesDir)) {
  console.log('No themes directory found');
  process.exit(0);
}

const themes = fs.readdirSync(themesDir)
  .filter(dir => fs.existsSync(path.join(themesDir, dir, 'theme.yaml')))
  .map(dir => {
    const cfg = yaml.load(fs.readFileSync(path.join(themesDir, dir, 'theme.yaml'), 'utf8')) || {};
    return { code: cfg.code || dir, name: cfg.name || dir, enabled: cfg.enabled !== false };
  });

console.table(themes);





