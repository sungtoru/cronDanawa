<?php
class Danawa
{
    const BRAND_LIST_URL = 'https://auto.danawa.com/auto/';
    const MODEL_LIST_URL = 'https://auto.danawa.com/auto/?Work=brand&Brand';
    const MODEL_INFO_URL = 'https://auto.danawa.com/auto/?Work=model&Model';

    public function __construct()
    {
        $this->_includeLibraries();
    }

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
    }

    public function _includeLibraries()
    {
        require __DIR__ . '/lib/simple_html_dom.php';
    }

    private function _getText($parent, $selector) {
        $ele = $parent->find($selector, 0);
        if (!$ele) {
            return "";
        }
        return trim($ele->text());
    }

    private function _getAttr($parent, $selector, $attr) {
        $ele = $parent->find($selector, 0);
        if (!$ele) {
            return "";
        }
        return trim($ele->attr[$attr]);
    }

    private function _error($message) {
        echo $message . "\n\n";
    }

    public function _sleep() {
        usleep(rand(2500, 7500) * 1000);
    }

    private function _html($url) {
        $opt = array(
            'http'=>array(
                'method'=>"GET",
                'header'=>
                    "Accept-language: ko,en-GB;q=0.9,en-US;q=0.8,en;q=0.7,en-AU;q=0.6\r\n" .
                    "User-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36\r\n" .
                    "Host: auto.danawa.com\r\n" .
                    "Referer: https://auto.danawa.com/auto/\r\n"
            )
        );
        $context = stream_context_create($opt);
        return file_get_contents($url, false, $context);
    }

    private function _htmlObject($url) {
        $opt = array(
            'http'=>array(
                'method'=>"GET",
                'header'=>
                    "Accept-language: ko,en-GB;q=0.9,en-US;q=0.8,en;q=0.7,en-AU;q=0.6\r\n" .
                    "User-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36\r\n" .
                    "Host: auto.danawa.com\r\n" .
                    "Referer: https://auto.danawa.com/auto/\r\n"
            )
        );
        $context = stream_context_create($opt);
        return file_get_html($url, false, $context);
    }


    function getDigits($value) {
        return preg_replace('/[^\d]/', '', $value);
    }

    public function getBrands()
    {
        $html = $this->_htmlObject(self::BRAND_LIST_URL);
        $domestic = $html->find('div.domestic > ul.brandList > li');
        $imported = $html->find('div.import > ul.brandList > li');
        $result = array();
        foreach(array('domestic' => $domestic, 'imported' => $imported) as $origin => $ul)
        {
            foreach($ul as $li)
            {
                $href = $this->_getAttr($li, 'a', 'href');
                $urlParse = parse_url($href);
                parse_str($urlParse['query'], $query);
                $brandName = $this->_getText($li, 'span.name');
                $result[$origin][$brandName] = array(
                    'brandCode' => $query['Brand']
                );
            }
        }
        $html->clear();

        return $result;
    }

    public function getModels($brands)
    {
        if(!$brands)
        {
            return;
        }
        $result = array();
        foreach($brands as $origin => $row)
        {
            foreach($row as $brandName => $brand)
            {
                $html = $this->_htmlObject(self::MODEL_LIST_URL . "=" . $brand['brandCode']);
                $contents = $html->find('dl.modelBox > dd');
                foreach($contents as $ul)
                {
                    foreach($ul->find('li') as $li)
                    {
                        $modelCode = $li->attr['code'];
                        $class = $li->attr['class'];
                        $statCode = 'normal';
                        if($class == 'comming' || $class == 'stock')
                        {
                            $statCode = $class;
                        }
                        $result[$origin][$brandName][] = array (
                             'modelName' => $this->_getText($li, 'span.name')
                            ,'modelCode' => $modelCode
                            ,'statCode' => $statCode
                        );
                    }
                }
                $html->clear();
                sleep(1);
                //$this->_sleep();
            }
        }

        return $result;
    }

    public function getLineups($models)
    {
        if(!$models)
        {
            return;
        }
        $result = array();
        foreach($models as $origin => $brands)
        {
            foreach($brands as $brandName => $brand)
            {
                foreach($brand as $model)
                {
                    //$html = $this->_htmlObject(self::MODEL_INFO_URL . "=" . $model['modelCode']);
                    $html = $this->_htmlObject('https://auto.danawa.com/auto/?Work=model&Model=4086');
                }
            }
        }

    }

    public function _run()
    {
        //$brands = $this->getBrands();
        //$models = $this->getModels($brands);
        //$lineups = $this->getLineups($models);
        $this->getLineups(
            array(
                'domestic' => array(
                    '현대' => array(
                        'modelCode' => 4086
                    )
                )
            )
        );
    }

}

$danawa = new Danawa();
$danawa->_run();