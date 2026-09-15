/**
 * Write a version into every place the plugin declares one.
 *
 * The zip's plugin header is what WordPress compares against, so a build whose
 * header doesn't match its release tag gets offered as an update forever.
 *
 * Usage: npm run set-version 1.9.0
 *
 * Named `set-version` rather than `version`, which npm reserves as a lifecycle
 * hook and would run with no arguments.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = path.join( __dirname, '..' );
const PLUGIN_FILE = path.join( ROOT, 'wordpress-tools.php' );
const PACKAGE_FILE = path.join( ROOT, 'package.json' );

// Semver, with an optional dot-separated pre-release suffix.
const VERSION_PATTERN = /^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/;

function setVersion( version ) {
	if ( ! VERSION_PATTERN.test( version ) ) {
		throw new Error( `Invalid version: ${ version }` );
	}

	let plugin = fs.readFileSync( PLUGIN_FILE, 'utf8' );

	const replacements = [
		[ /^(\s*\*\s*Version:\s*).*$/m, `$1${ version }` ],
		[ /(define\(\s*'WPT_VERSION',\s*')[^']*('\s*\))/, `$1${ version }$2` ],
	];

	replacements.forEach( ( [ pattern, replacement ] ) => {
		if ( ! pattern.test( plugin ) ) {
			throw new Error( `Could not find ${ pattern } in wordpress-tools.php` );
		}

		plugin = plugin.replace( pattern, replacement );
	} );

	fs.writeFileSync( PLUGIN_FILE, plugin );

	const pkg = JSON.parse( fs.readFileSync( PACKAGE_FILE, 'utf8' ) );
	pkg.version = version;
	fs.writeFileSync( PACKAGE_FILE, `${ JSON.stringify( pkg, null, '\t' ) }\n` );

	return version;
}

module.exports = setVersion;

if ( require.main === module ) {
	try {
		const version = setVersion( process.argv[ 2 ] );
		console.log( `Set version to ${ version }` );
	} catch ( error ) {
		console.error( `set-version: ${ error.message }` );
		process.exit( 1 );
	}
}
