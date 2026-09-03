(function () {
	'use strict';
	if ( ! ( 'modelContext' in document ) ) {
		return;
	}
	var slug = ( window.amAgentActivity && window.amAgentActivity.slug ) || '';

	function beacon( tool ) {
		try {
			navigator.sendBeacon( '/wp-json/agent-metrics/v1/agent-activity', JSON.stringify( { kind: 'declared', tool: tool, slug: slug } ) );
		} catch ( e ) {}
	}

	function getJson( url ) {
		return fetch( url ).then( function ( r ) {
			return r.json();
		} );
	}

	function getText( url ) {
		return fetch( url ).then( function ( r ) {
			return r.text();
		} );
	}

	var tools = [
		{
			name: 'get_page_content',
			title: 'Get page content',
			description: 'Returns the content of the current page as markdown.',
			inputSchema: { type: 'object', properties: {}, required: [] },
			execute: function () {
				return getText( '/wp-json/agent-metrics/v1/page-markdown?slug=' + encodeURIComponent( slug ) );
			}
		},
		{
			name: 'search_site',
			title: 'Search site',
			description: 'Searches pages on this site and returns matching titles, URLs and excerpts.',
			inputSchema: { type: 'object', properties: { query: { type: 'string' } }, required: [ 'query' ] },
			execute: function ( args ) {
				return getJson( '/wp-json/wp/v2/search?search=' + encodeURIComponent( args.query ) + '&per_page=10' ).then( function ( rows ) {
					return ( rows || [] ).map( function ( row ) {
						return { title: row.title || '', url: row.url || '', excerpt: row.excerpt || '' };
					} );
				} );
			}
		},
		{
			name: 'get_site_map',
			title: 'Get site map',
			description: 'Returns the site map for agents: the list of pages with descriptions (llms.txt format).',
			inputSchema: { type: 'object', properties: {}, required: [] },
			execute: function () {
				return getText( '/llms.txt' );
			}
		}
	];

	tools.forEach( function ( tool ) {
		var execute = tool.execute;
		tool.annotations = { readOnlyHint: true };
		tool.execute = function ( args ) {
			beacon( tool.name );
			return execute( args );
		};
		document.modelContext.registerTool( tool, {} );
	} );
})();
