<?php namespace Cscheide\ArticleExtractor;

use Goose\Client as GooseClient;
use PHPHtmlParser\Dom;

 
class ArticleExtractor {
 
	public function getArticleText($url) {
		$text = null;
		$method = "goose";

		// Try to get the article using Goose first
		$goose = new GooseClient(['image_fetch_best' => false]);
		$article = $goose->extractContent($url);
	
		// If Goose failed
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

			// Get a list of qualifying nodes we want to evaluate as the top node for content
			$contentList = $this->buildAllNodeList($dom->root);

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

//				log_debug("Element:\t". $node->tag->name() . "\tTotal WC:\t" . $this_element_wc . "\tTotal White:\t" . $this_element_whitecount . "\tRatio:\t" . number_format($this_element_wc_ratio,2) . "\tElement WC:\t" . $my_wc_contribution . "\tChildren WC:\t" . $children_wc . "\tChild Contributors:\t" . $children_num . "\tBest WC:\t" . $best_div_wc . "\tBest Ratio:\t" . number_format($best_div_wc_ratio,2) . " " . $node->getAttribute('class') . "\n");

				if (strpos($node->getAttribute('class'), 'brightcovevideosingle') !== false) {
				
					foreach($node->getChildren() as $child) {
						$this_element_wc2 = str_word_count($child->text(true));
//						log_debug("Child:\t". $child->tag->name() . " Class: " . $node->getAttribute('class') . "\tTotal WC:\t" . $this_element_wc2 . "\n");
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

//						log_debug("\tNot new best element - less than 10% improvement and not better wc ratio\n");
					
					}
					// If it the previous was zero, then go ahead and update the best
					else {
						$best_div_wc = $my_wc_contribution;
						$best_div_wc_ratio = $this_element_wc_ratio;
						$best_div = $node;
//						log_debug("\t *** New best element ***\n");
					}
				}
			}
			if ($best_div) {
				$text = html_entity_decode($best_div->text(true));
				$method = "custom";
			}
			else {
				$method = null;
			}
		}
		else {
			$text = $article->getCleanedArticleText();
		}

		// Convert items to UTF-8
		$clean_utf_title = iconv(mb_detect_encoding($article->getTitle(), mb_detect_order(), true), "UTF-8", $article->getTitle());
		$clean_utf_text = iconv(mb_detect_encoding($text, mb_detect_order(), true), "UTF-8", $text);

		return ['title'=>$clean_utf_title,'text'=>$clean_utf_text,'method'=>$method];
	}

	function buildAllNodeList($element) {

		$return_array = array();

		if($element->getTag()->name() != "text") {

			// Look at each child div element
			if ($element->hasChildren()) {

				foreach($element->getChildren() as $child)
				{
					// Push the children's children
					$return_array = array_merge($return_array, array_values($this->buildAllNodeList($child)));
	
					// Include the following tags in the counts for children and number of words
					if ($child->tag->name() == 'body' || $child->tag->name() == 'form' || $child->tag->name() == 'main' || $child->tag->name() == 'div' || $child->tag->name() == 'ul' || $child->tag->name() == 'li' || $child->tag->name() == 'table' || $child->tag->name() == 'span' || $child->tag->name() == 'section' || $child->tag->name() == 'article') {
						array_push($return_array, $child);
					}
				}
			}
		}
		return $return_array;
	}
}



?>