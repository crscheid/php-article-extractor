<?php
 
use Cscheide\ArticleExtractor\ArticleExtractor;
 
class ExtractorTest extends PHPUnit_Framework_TestCase {

	private $problem_sites = [
//	Still need work on these sites
//		 'http://www.smithsonianmag.com/science-nature/day-nimbus-weather-satellie-180961686/',

//	These sites are ok
		 'http://www.slate.com/articles/news_and_politics/war_stories/2017/01/trump_talks_about_himself_complains_about_media_at_first_official_event.html',
		 'http://www.nhregister.com/opinion/20170116/poor-elijahs-almanack-some-choice-observations',
		 'http://www.mckinsey.com/industries/financial-services/our-insights/engaging-customers-the-evolution-of-asia-pacific-digital-banking?cid=other-eml-alt-mip-mck-oth-1701',
		 'https://www.fastcompany.com/3067246/innovation-agents/the-unexpected-design-challenge-behind-slacks-new-threaded-conversations',
	];

 
	public function testProblemSites()
	{
		foreach($this->problem_sites as $url) {
			$parser = new ArticleExtractor();
			echo "Testing: " . $url . "\n";
			$this->assertNotEmpty($parser->getArticleText($url)['text']);
		}
	}


}