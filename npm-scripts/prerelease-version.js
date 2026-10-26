/**
 * Compute the pre-release version for a branch build: <base>-beta.<run>.<branch-slug>.
 *
 * The run comes before the slug so PHP's version_compare() orders builds by run, not branch name.
 * The base is package.json's version, or the patch after --min (the latest stable) when that's higher.
 *
 * Usage: node npm-scripts/prerelease-version.js <branch> <run> [--min <version>]
 */

const branchSlug = require( './branch-slug' );
const pkg = require( '../package.json' );
const CORE_PATTERN = /^\d+\.\d+\.\d+$/;

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

function nextPatch( version ) {
	const [ major, minor, patch ] = version.split( '.' ).map( Number );

	return `${ major }.${ minor }.${ patch + 1 }`;
}

function prereleaseVersion( branch, run, min ) {
	let base = String( pkg.version ).split( '-' )[ 0 ];

	if ( ! CORE_PATTERN.test( base ) ) {
		throw new Error(
			`package.json version is not a plain semver version: ${ pkg.version }`
		);
	}

	if ( min && ! CORE_PATTERN.test( min ) ) {
		throw new Error(
			`Latest release is not a plain semver version: ${ min }`
		);
	}

	// A base at or below the latest stable would sort under it, so no site would be offered the build.
	if ( min && compareCore( base, min ) <= 0 ) {
		base = nextPatch( min );
	}

	if ( ! /^\d+$/.test( String( run ) ) ) {
		throw new Error( `Run number is not numeric: ${ run }` );
	}

	const slug = branchSlug( branch );

	if ( ! slug ) {
		throw new Error( `Branch name produced an empty slug: ${ branch }` );
	}

	return `${ base }-beta.${ run }.${ slug }`;
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
