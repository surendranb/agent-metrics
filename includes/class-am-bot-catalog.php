<?php
defined( 'ABSPATH' ) || exit;

class AM_Bot_Catalog {

	private static $bots = array(
		'gptbot'            => array( 'name' => 'GPTBot',                 'category' => 'training',  'patterns' => array( 'gptbot' ) ),
		'oai-searchbot'     => array( 'name' => 'OAI-SearchBot',          'category' => 'search',    'patterns' => array( 'oai-searchbot' ) ),
		'chatgpt-user'      => array( 'name' => 'ChatGPT User',           'category' => 'on-demand', 'patterns' => array( 'chatgpt-user', 'chatgpt' ) ),
		'claudebot'         => array( 'name' => 'ClaudeBot',              'category' => 'training',  'patterns' => array( 'claudebot' ) ),
		'claude-searchbot'  => array( 'name' => 'Claude SearchBot',       'category' => 'search',    'patterns' => array( 'claude-searchbot' ) ),
		'claude-user'       => array( 'name' => 'Claude User',            'category' => 'on-demand', 'patterns' => array( 'claude-user' ) ),
		'claude-web'        => array( 'name' => 'Claude Web',             'category' => 'on-demand', 'patterns' => array( 'claude-web' ) ),
		'anthropic-ai'      => array( 'name' => 'Anthropic AI',           'category' => 'training',  'patterns' => array( 'anthropic-ai', 'anthropicai' ) ),
		'ccbot'             => array( 'name' => 'Common Crawl',           'category' => 'training',  'patterns' => array( 'ccbot' ) ),
		'bytespider'        => array( 'name' => 'Bytespider (ByteDance)', 'category' => 'training',  'patterns' => array( 'bytespider' ) ),
		'amazonbot'         => array( 'name' => 'Amazonbot',              'category' => 'training',  'patterns' => array( 'amazonbot' ) ),
		'applebot-ext'      => array( 'name' => 'Applebot Extended',      'category' => 'training',  'patterns' => array( 'applebot-extended' ) ),
		'applebot'          => array( 'name' => 'Applebot',               'category' => 'search',    'patterns' => array( 'applebot' ) ),
		'google-ext'        => array( 'name' => 'Google-Extended',        'category' => 'training',  'patterns' => array( 'google-extended' ) ),
		'googleother'       => array( 'name' => 'GoogleOther',            'category' => 'training',  'patterns' => array( 'googleother', 'google-other' ) ),
		'perplexitybot'     => array( 'name' => 'PerplexityBot',          'category' => 'search',    'patterns' => array( 'perplexitybot' ) ),
		'perplexity-user'   => array( 'name' => 'Perplexity User',        'category' => 'on-demand', 'patterns' => array( 'perplexity-user' ) ),
		'youbot'            => array( 'name' => 'YouBot (You.com)',       'category' => 'search',    'patterns' => array( 'youbot' ) ),
		'researchbot'       => array( 'name' => 'researchbot (You.com)',  'category' => 'search',    'patterns' => array( 'researchbot' ) ),
		'dataforseobot'     => array( 'name' => 'DataForSeoBot',          'category' => 'search',    'patterns' => array( 'dataforseobot' ) ),
		'exabot'            => array( 'name' => 'ExaBot',                 'category' => 'search',    'patterns' => array( 'exabot' ) ),
		'petalbot'          => array( 'name' => 'PetalBot',               'category' => 'search',    'patterns' => array( 'petalbot' ) ),
		'duckassistbot'     => array( 'name' => 'DuckAssistBot',          'category' => 'search',    'patterns' => array( 'duckassistbot' ) ),
		'falkor'            => array( 'name' => 'Falkor (Wave)',          'category' => 'search',    'patterns' => array( 'falkor' ) ),
		'brave'             => array( 'name' => 'Brave Search',           'category' => 'search',    'patterns' => array( 'brave-search' ) ),
		'seekrbot'          => array( 'name' => 'SeekrBot',               'category' => 'search',    'patterns' => array( 'seekrbot' ) ),
		'timpibot'          => array( 'name' => 'TimpiBot',               'category' => 'search',    'patterns' => array( 'timpibot' ) ),
		'meta-agent'        => array( 'name' => 'Meta ExternalAgent',     'category' => 'training',  'patterns' => array( 'meta-externalagent' ) ),
		'meta-fetcher'      => array( 'name' => 'Meta ExternalFetcher',   'category' => 'training',  'patterns' => array( 'meta-externalfetcher' ) ),
		'mistralai-user'    => array( 'name' => 'Mistral AI User',        'category' => 'on-demand', 'patterns' => array( 'mistralai-user' ) ),
		'mistralai-index'   => array( 'name' => 'Mistral AI Index',       'category' => 'search',    'patterns' => array( 'mistralai-index' ) ),
		'bingbot'           => array( 'name' => 'Bingbot',                'category' => 'search',    'patterns' => array( 'bingbot' ) ),
		'cohere'            => array( 'name' => 'Cohere AI',              'category' => 'training',  'patterns' => array( 'cohere-ai', 'cohereai' ) ),
		'diffbot'           => array( 'name' => 'Diffbot',                'category' => 'training',  'patterns' => array( 'diffbot' ) ),
		'imagesiftbot'      => array( 'name' => 'ImageSiftBot',           'category' => 'training',  'patterns' => array( 'imagesiftbot' ) ),
		'brightbot'         => array( 'name' => 'Brightbot',              'category' => 'training',  'patterns' => array( 'brightbot-1', 'brightbot' ) ),
		'omgili'            => array( 'name' => 'Omgili',                 'category' => 'training',  'patterns' => array( 'omgili', 'omgilibot' ) ),
		'firecrawl'         => array( 'name' => 'Firecrawl',              'category' => 'training',  'patterns' => array( 'firecrawl' ) ),
		'glasp'             => array( 'name' => 'Glasp',                  'category' => 'training',  'patterns' => array( 'glasp' ) ),
		'synapseai'         => array( 'name' => 'SynapseAI',              'category' => 'training',  'patterns' => array( 'synapseai' ) ),
		'kangaroobot'       => array( 'name' => 'KangarooBot',            'category' => 'search',    'patterns' => array( 'kangaroobot' ) ),
		'ai2bot'            => array( 'name' => 'AI2Bot (Allen AI)',      'category' => 'training',  'patterns' => array( 'ai2bot', 's2bot' ) ),
	);

	public static function get( $slug ) {
		return self::$bots[ $slug ] ?? null;
	}

	public static function match( $ua ) {
		$ua = strtolower( (string) $ua );
		if ( '' === $ua ) {
			return null;
		}
		foreach ( self::$bots as $slug => $bot ) {
			foreach ( $bot['patterns'] as $pattern ) {
				if ( false !== strpos( $ua, $pattern ) ) {
					return array(
						'slug'     => $slug,
						'name'     => $bot['name'],
						'category' => $bot['category'],
					);
				}
			}
		}
		return null;
	}

	public static function count() {
		return count( self::$bots );
	}
}
