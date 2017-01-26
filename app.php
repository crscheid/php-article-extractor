<?php

require __DIR__ . '/vendor/autoload.php';


use Goose\Client as GooseClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PHPHtmlParser\Dom;

// McKinsey - very poorly formed HTML
// Slate - article had text that had more in the paragraph than others


// TODO: Check out why the raw doc works for smithsonian and mckinsey, but not the cleaned doc


// Setup the logger
date_default_timezone_set('UTC');
$logger = new Logger('parser-harness');
$logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());


$test_urls = [
// 'http://www.slate.com/blogs/browbeat/2017/01/18/will_and_grace_is_returning_to_nbc_for_a_10_episode_revival.html',
// 'http://www.nhregister.com/opinion/20170116/poor-elijahs-almanack-some-choice-observations',
// 'http://www.slate.com/blogs/the_slatest/2017/01/22/audit_was_an_excuse_and_trump_is_never_releasing_tax_returns.html',
// 'http://www.slate.com/articles/news_and_politics/war_stories/2017/01/trump_talks_about_himself_complains_about_media_at_first_official_event.html',
 'http://www.mckinsey.com/industries/financial-services/our-insights/engaging-customers-the-evolution-of-asia-pacific-digital-banking?cid=other-eml-alt-mip-mck-oth-1701',
// 'https://www.fastcompany.com/3067246/innovation-agents/the-unexpected-design-challenge-behind-slacks-new-threaded-conversations',
// 'http://www.smithsonianmag.com/science-nature/day-nimbus-weather-satellie-180961686/?utm_source=smithsoniansciandnat&utm_medium=email&utm_campaign=2016701-science&spMailingID=27585738&spUserID=NzQwNDU3NTcxNDES1&spJobID=963439645&spReportId=OTYzNDM5NjQ1S0',
];


foreach($test_urls as $url) {

	log_debug("Checking: " . $url);

	// Try to get the article
	$goose = new GooseClient(['image_fetch_best' => false]);
	$article = $goose->extractContent($test_urls[0]);
	
	if ($article->getCleanedArticleText() == null) {
	
		// Get the HTML from goose
		$html_string = $article->getRawHtml();
	
		// Ok then try it a different way
		$dom = new Dom;
		$dom->load($html_string, ['whitespaceTextNode' => false]);
		
		// First, just completely remove the items we don't even care about		
		$scriptNodes = $dom->find('script, style, header, footer, input, form, button, aside, meta, link');
		foreach($scriptNodes as $node) {
			$node->delete();
			unset($node);
		}		

		$best_div = null;	

		$html = $dom->outerHtml;
		var_dump($html);		

		// Get a list of qualifying nodes we want to evaluate as the top node for content
		$contentList = buildAllNodeList($dom->root);

		// Find a target best element
		foreach($contentList as $node) {

            // Calculate the wordcount, whitecount, and ratio for this element after stripping out all tags
            $this_element_wc = str_word_count($node->text(true));
            $this_element_whitecount = substr_count($node->text(true), ' ');
            $this_element_wc_ratio = -1;
            
            if ($this_element_wc != 0) {
                $this_element_wc_ratio = $this_element_whitecount / $this_element_wc;
            }

            // Calculate the word count contribution for all children div's
            $children_wc = 0;
            $children_num = 0;
			foreach($node->getChildren() as $child) {
				if ($child->tag->name() == 'body' || $child->tag->name() == 'form' || $child->tag->name() == 'main' || $child->tag->name() == 'div' || $child->tag->name() == 'ul' || $child->tag->name() == 'li' || $child->tag->name() == 'table' || $child->tag->name() == 'span' || $child->tag->name() == 'section' || $child->tag->name() == 'article') {
					$children_num++;
					$children_wc += str_word_count($child->text(true));
				}
            }

            // This is the contribution for this particular div not including the children types above
            $my_wc_contribution = $this_element_wc - $children_wc;

            log_debug("Element:\t". $node->tag->name() . "\tTotal WC:\t" . $this_element_wc . "\tTotal White:\t" . $this_element_whitecount . "\tRatio:\t" . number_format($this_element_wc_ratio,2) . "\tElement WC:\t" . $my_wc_contribution . "\tChildren WC:\t" . $children_wc . "\tChild Contributors:\t" . $children_num . "\tBest WC:\t" . $best_div_wc . "\tBest Ratio:\t" . number_format($best_div_wc_ratio,2) . " " . $node->getAttribute('class') . "\n");

			if (strpos($node->getAttribute('class'), 'brightcovevideosingle') !== false) {
	            
	            foreach($node->getChildren() as $child) {
	            	$this_element_wc2 = str_word_count($child->text(true));
		            log_debug("Child:\t". $child->tag->name() . " Class: " . $node->getAttribute('class') . "\tTotal WC:\t" . $this_element_wc2 . "\n");
	            }
			}

            // Now check to see if this element appears better than any previous one
            
            // We do this by first checking to see if this elements WC contribution is greater than the previous
            if  ($my_wc_contribution > $best_div_wc) {
                
                // There are three conditions required for this candidate not to be chosen
                //      1. The previous best cannot be zero
                //      2. The new best is less than 5%
                //      3. The new element wc ratio is greater than the existing best element's ratio
                if (    $best_div_wc != 0 && 
                        ($my_wc_contribution / $best_div_wc) < 1.10 &&
                        $best_div_wc_ratio < $this_element_wc_ratio
                        ) {

                    log_debug("\tNot new best element - less than 10% improvement and not better wc ratio\n");
                    
                }
                // If it the previous was zero, then go ahead and update the best
                else {
                    $best_div_wc = $my_wc_contribution;
                    $best_div_wc_ratio = $this_element_wc_ratio;
                    $best_div = $node;
                    log_debug("\t *** New best element ***\n");
                }
            }
		}


		if ($best_div) {
			$text = html_entity_decode($best_div->text(true));
			log_info("Article Text Custom");	
		}
		else {
			log_info("Article Text Custom: NULL");	
		}


	}
	else {
		$text = $article->getCleanedArticleText();
		log_info("Article Text Parser");	
	}
}

// Convert items to UTF-8
$clean_utf_title = iconv(mb_detect_encoding($article->getTitle(), mb_detect_order(), true), "UTF-8", $article->getTitle());
$clean_utf_text = iconv(mb_detect_encoding($text, mb_detect_order(), true), "UTF-8", $text);

log_debug("Text: " . $clean_utf_text);



function buildAllNodeList($element) {

	$return_array = array();

	if($element->getTag()->name() != "text") {

		// Look at each child div element
		if ($element->hasChildren()) {

			foreach($element->getChildren() as $child)
			{
				// Push the children's children
				$return_array = array_merge($return_array, array_values(buildAllNodeList($child)));
	
				// Include the following tags in the counts for children and number of words
				if ($child->tag->name() == 'body' || $child->tag->name() == 'form' || $child->tag->name() == 'main' || $child->tag->name() == 'div' || $child->tag->name() == 'ul' || $child->tag->name() == 'li' || $child->tag->name() == 'table' || $child->tag->name() == 'span' || $child->tag->name() == 'section' || $child->tag->name() == 'article') {
					array_push($return_array, $child);
				}
			}
		}
	}
	return $return_array;
}

/*
 * Utility logging functions
 */
function log_debug($a) {
	global $logger;
	$logger->addDebug($a);
}
function log_info($a) {
	global $logger;
	$logger->addInfo($a);
}
function log_error($a) {
	global $logger;
	$logger->addError($a);
}
function log_warning($a) {
	global $logger;
	$logger->addWarning($a);
}

?>