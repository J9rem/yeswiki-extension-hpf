// Extract files that we need from the node_modules folder
// The extracted files are integrated to the repository, so production server don't need to
// have node installed
// Feature UUID : hpf-register-payment-action
// Feature UUID : hpf-payments-field

// Include fs and path module

const fs = require('fs-extra')
const path = require('path')

const basePath = path.join(__dirname, '../')

function copySync(src, dest, opts) {
  if (fs.existsSync(src)) {
    fs.copySync(path.join(basePath, src), path.join(basePath, dest), opts)
  } else {
    console.log(`${src} is not existing !`)
  }
}

// vuejs-datepicker
copySync(
    'node_modules/vuejs-datepicker/dist/vuejs-datepicker.min.js',
    'javascripts/vendor/vuejs-datepicker/vuejs-datepicker.min.js',
    { overwrite: true }
  )
copySync(
    'node_modules/vuejs-datepicker/LICENSE',
    'javascripts/vendor/vuejs-datepicker/LICENSE',
    { overwrite: true }
  )
// locale
// copySync(
//     'node_modules/vuejs-datepicker/dist/locale/translations',
//     'javascripts/vendor/vuejs-datepicker/locale',
//     { overwrite: true }
//   )
copySync(
    'node_modules/vuejs-datepicker/dist/locale/index.js',
    'javascripts/vendor/vuejs-datepicker/translations.min.js',
    { overwrite: true }
  )
