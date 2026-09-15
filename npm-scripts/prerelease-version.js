/**
 * Compute the pre-release version for a branch build.
 *
 * Format: <package.json version>-<branch-slug>.<YYYYMMDD>.<run>
 *
 * Usage: node npm-scripts/prerelease-version.js <branch> <run> [--min <version>]
 *
 * --min is the latest published stable version. The base version must be higher
 * than it, or the build sorts below stable and no site will ever be offered it.
 */

const branchSlug = require( './branch-slug' );
const pkg = require( '../package.json' );

function compareCore( a, b ) {
	const left = a.split( '.' ).map( Number );
	const right = b.split( '.' ).map( Number );

	for ( let i = 0; i < 3; i++ ) {
		if ( ( left[ i ] || 0 ) !== ( right[ i ] || 0 ) ) {
			return ( left[ i ] || 0 ) > ( right[ i ] || 0 ) ? 1 : -1;
		}
	}

	return 0;
}

function prereleaseVersion( branch, run, min ) {
	const base = String( pkg.version ).split( '-' )[ 0 ];

	if ( ! /^\d+\.\d+\.\d+$/.test( base ) ) {
		throw new Error( `package.json version is not a plain semver version: ${ pkg.version }` );
	}

	if ( min && compareCore( base, min ) <= 0 ) {
		throw new Error(
			`package.json is at ${ base }, which is not newer than the latest release (${ min }).\n` +
				'Bump the version in package.json before building a pre-release, or the build ' +
				'will sort below stable and no site will be offered it.'
		);
	}

	const slug = branchSlug( branch );

	if ( ! slug ) {
		throw new Error( `Branch name produced an empty slug: ${ branch }` );
	}

	const date = new Date().toISOString().slice( 0, 10 ).replace( /-/g, '' );

	return `${ base }-${ slug }.${ date }.${ run }`;
}

module.exports = prereleaseVersion;

if ( require.main === module ) {
	const [ branch, run ] = process.argv.slice( 2 );
	const minIndex = process.argv.indexOf( '--min' );
	const min = minIndex > -1 ? process.argv[ minIndex + 1 ] : '';

	try {
		process.stdout.write( prereleaseVersion( branch, run || '1', min ) );
	} catch ( error ) {
		console.error( `prerelease-version: ${ error.message }` );
		process.exit( 1 );
	}
}
