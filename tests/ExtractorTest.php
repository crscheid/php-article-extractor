<?php
 
use Cscheide\ArticleExtractor\ArticleExtractor;
 
class ExtractorTest extends PHPUnit_Framework_TestCase {
 
  public function testCnn()
  {
    $parser = new ArticleExtractor;
    $this->assertNotNull($parser->getArticleText('http://edition.cnn.com/2017/01/25/asia/china-sizes-up-donald-trump/index.html'));
  }

  public function testMcKinsey()
  {
    $parser = new ArticleExtractor;
    $this->assertNotNull($parser->getArticleText('http://www.mckinsey.com/industries/financial-services/our-insights/engaging-customers-the-evolution-of-asia-pacific-digital-banking?cid=other-eml-alt-mip-mck-oth-1701'));
  }
 
}