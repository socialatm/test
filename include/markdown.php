<?php
/**
 * @file include/markdown.php
 * @brief Some functions for BB conversions for Diaspora protocol.
 */

use Michelf\MarkdownExtra;

use League\HTMLToMarkdown\HtmlConverter;
use League\HTMLToMarkdown\Environment;
use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\ElementInterface;

require_once("include/oembed.php");
require_once("include/event.php");
require_once("include/html2bbcode.php");
require_once("include/bbcode.php");


/**
 * @brief Convert Markdown to bbcode.
 *
 * We don't want to support a bbcode specific markdown interpreter
 * and the markdown library we have is pretty good, but provides HTML output.
 * So we'll use that to convert to HTML, then convert the HTML back to bbcode,
 * and then clean up a few Diaspora specific constructs.
 *
 * @param string $s The message as Markdown
 * @param boolean $use_zrl default false
 * @param array $options default empty
 * @return string The message converted to bbcode
 */
function markdown_to_bb($s, $use_zrl = false, $options = []) {

	if(is_array($s)) {
		btlogger('markdown_to_bb called with array. ' . print_r($s, true), LOGGER_NORMAL, LOG_WARNING);
		return '';
	}

	$s = str_replace("&#xD;","\r",$s);
	$s = str_replace("&#xD;\n&gt;","",$s);

	$s = html_entity_decode($s,ENT_COMPAT,'UTF-8');

	// if empty link text replace with the url
	$s = preg_replace("/\[\]\((.*?)\)/ism",'[$1]($1)',$s);

	$x = [
			'text' => $s,
			'zrl' => $use_zrl,
			'options' => $options
	];
	/**
	 * @hooks markdown_to_bb_init
	 *   * \e string \b text - The message as Markdown and what will get returned
	 *   * \e boolean \b zrl
	 *   * \e array \b options
	 */
	call_hooks('markdown_to_bb_init', $x);

	$s = $x['text'];

	// Escaping the hash tags
	$s = preg_replace('/\#([^\s\#])/','&#35;$1',$s);

	$s = MarkdownExtra::defaultTransform($s);


	$preserve_lf = $options['preserve_lf'] ?? false;

	if($preserve_lf) {
		$s = str_replace(["\r","\n"],["",'<br>'],$s);
	}
	else {
		$s = str_replace("\r","",$s);
	}

	$s = str_replace('&#35;','#',$s);

	$s = html2bbcode($s);

	// $s = bb_code_protect($s);

	// Convert everything that looks like a link to a link
	if($use_zrl) {
		if (strpos($s,'[/img]') !== false) {
			$s = preg_replace_callback("/\[img\](.*?)\[\/img\]/ism", 'use_zrl_cb_img', $s);
			$s = preg_replace_callback("/\[img\=([0-9]*)x([0-9]*)\](.*?)\[\/img\]/ism", 'use_zrl_cb_img_x', $s);
		}
		$s = preg_replace_callback("/([^\]\=\{\/]|^)(https?\:\/\/)([a-zA-Z0-9\pL\:\/\-\?\&\;\.\=\_\~\#\%\$\!\+\,\@\(\)]+)([\,\.\:\;]\s|$)/ismu", 'use_zrl_cb_link',$s);
	}
	else {
		$s = preg_replace("/([^\]\=\{\/]|^)(https?\:\/\/)([a-zA-Z0-9\pL\:\/\-\?\&\;\.\=\_\~\#\%\$\!\+\,\@\(\)]+)([\,\.\:\;]\s|$)/ismu", '$1[url=$2$3]$2$3[/url]$4',$s);
	}

	// $s = bb_code_unprotect($s);

	// remove duplicate adjacent code tags
	$s = preg_replace("/(\[code\])+(.*?)(\[\/code\])+/ism","[code]$2[/code]", $s);


	/**
	 * @hooks markdown_to_bb
	 *   * \e string - The already converted message as bbcode
	 */
	call_hooks('markdown_to_bb', $s);

	return $s;
}

function use_zrl_cb_link($match) {
	$res = '';
	$is_zid = is_matrix_url(trim($match[0]));

	if($is_zid)
		$res = $match[1] . '[zrl=' . $match[2] . $match[3] . ']' . $match[2] . $match[3] . '[/zrl]' . $match[4];
	else
		$res = $match[1] . '[url=' . $match[2] . $match[3] . ']' . $match[2] . $match[3] . '[/url]' . $match[4];

	return $res;
}

function use_zrl_cb_img($match) {
	$res = '';
	$is_zid = is_matrix_url(trim($match[1]));

	if($is_zid)
		$res = '[zmg]' . $match[1] . '[/zmg]';
	else
		$res = $match[0];

	return $res;
}

function use_zrl_cb_img_x($match) {
	$res = '';
	$is_zid = is_matrix_url(trim($match[3]));

	if($is_zid)
		$res = '[zmg=' . $match[1] . 'x' . $match[2] . ']' . $match[3] . '[/zmg]';
	else
		$res = $match[0];

	return $res;
}

/**
 * @brief
 *
 * @param array $match
 * @return string
 */
function bb_to_markdown_share($match) {

	$matches = array();
	$attributes = $match[1];

	$author = "";
	preg_match("/author='(.*?)'/ism", $attributes, $matches);
	if ($matches[1] != "")
		$author = urldecode($matches[1]);

	$link = "";
	preg_match("/link='(.*?)'/ism", $attributes, $matches);
	if ($matches[1] != "")
		$link = $matches[1];

	$avatar = "";
	preg_match("/avatar='(.*?)'/ism", $attributes, $matches);
	if ($matches[1] != "")
		$avatar = $matches[1];

	$profile = "";
	preg_match("/profile='(.*?)'/ism", $attributes, $matches);
	if ($matches[1] != "")
		$profile = $matches[1];

	$posted = "";
	preg_match("/posted='(.*?)'/ism", $attributes, $matches);
	if ($matches[1] != "")
		$posted = $matches[1];

	// message_id is never used, do we still need it?
	$message_id = "";
	preg_match("/message_id='(.*?)'/ism", $attributes, $matches);
	if ($matches[1] != "")
		$message_id = $matches[1];

	if(! $message_id) {
		preg_match("/guid='(.*?)'/ism", $attributes, $matches);
		if ($matches[1] != "")
			$message_id = $matches[1];
	}


	$reldate = datetime_convert('UTC', date_default_timezone_get(), $posted, 'r');

	$headline = '';

	if ($avatar != "")
		$headline .= '[url=' . zid($profile) . '][img]' . $avatar . '[/img][/url]';

	// Bob Smith wrote the following post 2 hours ago

	$fmt = sprintf( t('%1$s wrote the following %2$s %3$s'),
		'[url=' . zid($profile) . ']' . $author . '[/url]',
		'[url=' . zid($link) . ']' . t('post') . '[/url]',
		$reldate
	);

	$headline .= $fmt . "\n\n";

	$text = $headline . trim($match[2]);

	return $text;
}


function bb_to_markdown_transform_tags($match) {
	return '#'. str_replace(' ', '_', $match[3]);
}


/**
 * @brief Convert bbcode to Markdown.
 *
 * @param string $Text The message as bbcode
 * @param array $options default empty
 * @return string The message converted to Markdown
 */
function bb_to_markdown($Text, $options = []) {

	/*
	 * Transform #tags, strip off the [url] and replace spaces with underscore
	 */

	$Text = preg_replace_callback('/#\[([zu])rl\=(.*?)\](.*?)\[\/[(zu)]rl\]/i', 'bb_to_markdown_transform_tags', $Text);

	$Text = preg_replace('/#\^\[([zu])rl\=(.*?)\](.*?)\[\/([zu])rl\]/i', '[$1rl=$2]$3[/$4rl]', $Text);

	// Converting images with size parameters to simple images. Markdown doesn't know it.
	$Text = preg_replace("/\[img\=([0-9]*)x([0-9]*)\](.*?)\[\/img\]/ism", '[img]$3[/img]', $Text);

	$Text = preg_replace_callback("/\[share(.*?)\](.*?)\[\/share\]/ism", 'bb_to_markdown_share', $Text);

	$x = [ 'bbcode' => $Text, 'options' => $options ];

	/**
	 * @hooks bb_to_markdown_bb
	 *   * \e string \b bbcode - The message as bbcode and what will get returned
	 *   * \e array \b options
	 */
	call_hooks('bb_to_markdown_bb', $x);

	$Text = $x['bbcode'];

	// Replace spoiler tag before BBcode conversion
	$Text = preg_replace("/\[\/?spoiler\]/is", "\n--- " .t('spoiler') . " ---\n", $Text);

	// Convert it to HTML - don't try oembed
	$Text = bbcode($Text, [ 'tryoembed' => false ]);

	// Now convert HTML to Markdown
	$Text = html2markdown($Text);

	//html2markdown adds backslashes infront of hashes after a new line. remove them
	$Text = str_replace("\n\#", "\n#", $Text);

	// If the text going into bbcode() has a plain URL in it, i.e.
	// with no [url] tags around it, it will come out of parseString()
	// looking like: <http://url.com>, which gets removed by strip_tags().
	// So take off the angle brackets of any such URL
	$Text = preg_replace("/<http(.*?)>/is", "http$1", $Text);

	// Remove empty zrl links
	$Text = preg_replace("/\[zrl\=\].*?\[\/zrl\]/is", "", $Text);

	// Replace unprocessed <br> in code
	$Text = str_replace("<br></br>", "\n", $Text);

	$Text = trim($Text);

	/**
	 * @hooks bb_to_markdown
	 *   * \e string - The already converted message as bbcode and what will get returned
	 */
	call_hooks('bb_to_markdown', $Text);

	return $Text;
}

/**
 * @brief Convert a HTML text into Markdown.
 *
 * This function uses the library league/html-to-markdown for this task.
 *
 * If the HTML text can not get parsed it will return an empty string.
 *
 * @param string $html The HTML code to convert
 * @param array $options an array of options to pass to the environment
 * @return string Markdown representation of the given HTML text, empty on error
 */
function html2markdown($html, $options = []) {
	$markdown = '';

	if(! $options) {
		$options = [
			'header_style' => 'setext', // Set to 'atx' to output H1 and H2 headers as # Header1 and ## Header2
			'suppress_errors' => true, // Set to false to show warnings when loading malformed HTML
			'strip_tags' => false, // Set to true to strip tags that don't have markdown equivalents. N.B. Strips tags, not their content. Useful to clean MS Word HTML output.
			'bold_style' => '**', // DEPRECATED: Set to '__' if you prefer the underlined style
			'italic_style' => '*', // DEPRECATED: Set to '_' if you prefer the underlined style
			'remove_nodes' => '', // space-separated list of dom nodes that should be removed. example: 'meta style script'
			'hard_break' => false, // Set to true to turn <br> into `\n` instead of `  \n`
			'list_item_style' => '-', // Set the default character for each <li> in a <ul>. Can be '-', '*', or '+'
		];
	}

	$environment = Environment::createDefaultEnvironment($options);
	$environment->addConverter(new TableConverter());
	$converter = new HtmlConverter($environment);

	try {
		$markdown = $converter->convert($html);
	} catch (InvalidArgumentException $e) {
		logger("Invalid HTML. HTMLToMarkdown library threw an exception.");
	}

	return $markdown;
}
