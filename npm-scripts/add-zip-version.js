const fs = require('fs');
const path = require('path');

const pkg = require('../package.json');
const version = pkg.version;

const src = path.join(__dirname, '..', 'wordpress-tools.zip');
const dest = path.join(__dirname, '..', `wordpress-tools-${version}.zip`);

fs.renameSync(src, dest);
