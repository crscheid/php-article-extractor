<?php
 
use Cscheide\ArticleExtractor\ArticleExtractor;
 
class ExtractorTest extends PHPUnit_Framework_TestCase {

	private $problem_sites = [
		'https://www.fastcompany.com/3067246/innovation-agents/the-unexpected-design-challenge-behind-slacks-new-threaded-conversations',
		'http://www.mckinsey.com/industries/financial-services/our-insights/engaging-customers-the-evolution-of-asia-pacific-digital-banking?cid=other-eml-alt-mip-mck-oth-1701',
		'http://www.nhregister.com/opinion/20170116/poor-elijahs-almanack-some-choice-observations',
		'http://www.slate.com/articles/news_and_politics/war_stories/2017/01/trump_talks_about_himself_complains_about_media_at_first_official_event.html',
		'https://t.co/kwb19AGfxl',
		'http://www.mckinsey.com/business-functions/digital-mckinsey/our-insights/the-next-generation-operating-model-for-the-digital-world?cid=reinventing-eml-alt-mip-mck-oth-1703',
		'http://www.slate.com/articles/news_and_politics/cover_story/2017/02/the_first_month_of_the_trump_presidency_has_been_more_cruel_and_destructive.html',
		'https://hbr.org/2017/03/the-promise-of-blockchain-is-a-world-without-middlemen',
	];

	private $known_problems = [
		// Some sort of problem with the DOM model reading the nesting of the <li> elements the wrong way - its backing out of the div too early
		'http://kotaku.com/nintendo-switch-the-kotaku-review-1792776350?utm_source=pocket&utm_medium=email&utm_campaign=pockethits',
	];
 
	public function testProblemSites()
	{
		echo "\n";
		
		foreach($this->problem_sites as $url) {
			$parser = new ArticleExtractor();
			echo "Testing: " . $url . "\n";
			$this->assertNotEmpty($parser->getArticleText($url)['text']);
		}
	}


}