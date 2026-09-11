/**
 * Convert a branch name to a version-safe slug.
 *
 * Must stay in sync with Updates::branch_slug() in includes/classes/Updates.php —
 * the build, the `branch:<slug>` update channel and the pre-release cleanup all
 * have to derive the same slug or they silently stop matching each other.
 *
 * Usage: node npm-scripts/branch-slug.js <branch-name>
 */

function branchSlug( branch ) {
	return String( branch || '' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );
}

module.exports = branchSlug;

if ( require.main === module ) {
	const slug = branchSlug( process.argv[ 2 ] );

	if ( ! slug ) {
		console.error( 'branch-slug: branch name produced an empty slug' );
		process.exit( 1 );
	}

	process.stdout.write( slug );
}
