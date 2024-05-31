<?php
/*
프로젝트명 : 다나와 자동차 > 자동차 백과 크롤링
작업일 : 2024-04-19
작업자 : 오성태
*/
class Danawa
{
    const BRAND_LIST_URL = 'https://auto.danawa.com/auto/';
    const MODEL_LIST_URL = 'https://auto.danawa.com/auto/?Work=brand&Brand';
    const MODEL_INFO_URL = 'https://auto.danawa.com/auto/?Work=model&Model';
    const MODEL_OPTION_URL = 'https://auto.danawa.com/newcar/?Work=estimate';

    public function __construct()
    {
        $this->_includeLibraries();
    }

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
    }

    private function _includeLibraries()
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

    private function _error($message)
    {
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

    private function _setReg($path, $contents)
    {
        file_put_contents( __DIR__ . '/_reg/' .$path, json_encode($contents) );
    }

    private function _getReg($fileName)
    {
       return json_decode(file_get_contents(__DIR__ . '/_reg/'. $fileName), true);
    }


    private function _getDigits($value) {
        return preg_replace('/[^\d]/', '', $value);
    }

    private function _dnwDecode($input)
    {
        $_keyStr = 'ABCDEFGHIJKLMNQPORSTVUWXYZabcdefghjiklmnoqprstuvwxyz0123456789+/=';
        $output = '';
        $chr1 = $chr2 = $chr3 = '';
        $enc1 = $enc2 = $enc3 = $enc4 = '';
        $i = 0;

        $input = preg_replace('/[^A-Za-z0-9\+\/\=]/', '', $input);

        do {
            $enc1 = strpos($_keyStr, substr($input, $i++, 1));
            $enc2 = strpos($_keyStr, substr($input, $i++, 1));
            $enc3 = strpos($_keyStr, substr($input, $i++, 1));
            $enc4 = strpos($_keyStr, substr($input, $i++, 1));

            $chr1 = ($enc1 << 2) | ($enc2 >> 4);
            $chr2 = (($enc2 & 15) << 4) | ($enc3 >> 2);
            $chr3 = (($enc3 & 3) << 6) | $enc4;

            $output .= chr($chr1);

            if ($enc3 != 64) {
                $output .= chr($chr2);
            }

            if ($enc4 != 64) {
                $output .= chr($chr3);
            }
        } while ($i < strlen($input));

        return $output;
    }

    

    public function getBrands()
    {
        $html = $this->_htmlObject(self::BRAND_LIST_URL);
        $domestic = $html->find('div.domestic > ul.brandList > li');
        $imported = $html->find('div.import > ul.brandList > li');
        $result = array();
        $sort = 1;
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
                    ,'sort' => $sort
                );
                $sort ++;
            }
        }
        $html->clear();

        $this->_setReg('brands.json', $result);
    }

    public function getLineups()
    {
        $models = $this->_getReg('models.json');
        if(!$models)
        {
            return;
        }
        //$result = array();
        $lineups = array();
        foreach($models as $origin => $brands)
        {
            foreach($brands as $brandName => $brand)
            {
                foreach($brand as $model)
                {
                    $html = $this->_htmlObject(self::MODEL_INFO_URL . "=" . $model['modelCode']);
                    foreach($html->find('div.price_contents > dl > dt.price_title') as $lineup)
                    {
                        $lineupIdx = $this->_getAttr($lineup, 'button.button_updown', 'data-lineup');
                        $lineupRelease = $this->_getText($lineup, 'div.name > span.year');
                        $lineups[$origin][$model['brandCode']][$model['modelCode']][] = array(
                            'lineupCode' => $lineupIdx
                            ,'lineupName' => $this->_getText($lineup, 'div.name > strong')
                            ,'release' => str_replace(array('(', ')'), '', $lineupRelease)
                        );
                    }
                    $html->clear();
                }
            }
        }

        echo "lineups completed \n";
        $this->_setReg('lineups.json', $lineups);
    }

    public function getTrims()
    {
        $data = $this->_getReg('lineups.json');
        if(!$data)
        {
            return;
        }
        $trims = array();

        foreach($data as $origin => $brand)
        {
            foreach($brand as $brandCode => $modelArr)
            {
                foreach($modelArr as $modelCode=>$lineupArr)
                {
                    foreach($lineupArr as $key=>$row)
                    {
                        $html = $this->_htmlObject(self::MODEL_INFO_URL . "=" . $modelCode . '&Lineup=' . $row['lineupCode']);
                        
                        foreach($html->find('dd.price_list') as $trim)
                        {
                            foreach($trim->find('ul > li') as $li)
                            {
                                $trimLineup = $this->_getAttr($li, 'input[name="compItemCk"]', 'lineup');
                                $trimHtml = $this->_getAttr($li, 'input[name="compItemCk"]', 'class');
                                $explodeTrim = explode('_', $trimHtml);
                                $trimIdx = $explodeTrim[1];

                                if($row['lineupCode'] == $trimLineup)
                                {
                                    $thtml = $this->_htmlObject(self::MODEL_OPTION_URL . "&Code=" . $brandCode . $modelCode . $row['lineupCode'] . $trimIdx . '&Conf=@@PG@S20@@@3@15000@CashS@@@');

                                    if(!$thtml)
                                    {
                                        continue;
                                    }
            
                                    preg_match_all('/estmDataAuto\[\'T'.$trimIdx.'\'\] = \'(.*?)\'/', $thtml, $trimData, PREG_SET_ORDER);
                                    if(!$trimData)
                                    {
                                        continue;
                                    }
                                    $a = gzuncompress(base64_decode($trimData[0][1]));
                                    $b = $this->_dnwDecode($a);
                                    $c = urldecode($b);
                                    $d = explode('^', $c);
                                    $e = str_replace('&nbsp;', ' ', $d);

                                    $f = explode('|', $e[22]);

                                    $trims[$origin][$brandCode][$modelCode][$row['lineupCode']][$trimIdx] = array(
                                        'trimName' => $this->_getText($li, 'div.item.name > label')
                                        ,'engine' => $this->_getText($li, 'div.item.engine')
                                        ,'milease' => $this->_getText($li, 'div.item.mileage')
                                        ,'price' => $this->_getText($li, 'span.item.price')
                                        ,'brandName' => $row['brandName']
                                        ,'modelName' => $row['modelName']
                                        ,'lineupName' => $row['lineupName']
                                        ,'specFuelName'=>str_replace(array('#options',','), '', explode(':', $f[0])[1])
                                        ,'specDisplace'=>str_replace(array('#options',','), '', explode(':', $f[1])[1])
                                    );
                                    
                                }
                                else
                                {
                                    continue;
                                }
                                
                            }
                        }
                        $html->clear();
                        sleep(1);
                    }
                    
                }
                

            }
        }
        echo "trims completed \n";
        $this->_setReg('trims.json', $trims);
    }

    public function getOptions()
    {
        $data = $this->_getReg('trims.json');
        if(!$data)
        {
            return;
        }
        $options = array();
        //$startTime = microtime(true);
        
        foreach($data['domestic'] as $brandCode => $items) 
        {
            foreach($items as $modelCode => $models)
            {
                foreach($models as $lineupCode => $lineups)
                {
                    foreach($lineups as $trimIdx => $trims)
                    {
                        $html = $this->_htmlObject(self::MODEL_OPTION_URL . "&Code=" . $brandCode . $modelCode . $lineupCode . $trimIdx . '&Conf=@@PG@S20@@@3@15000@CashS@@@');

                        if(!$html)
                        {
                            continue;
                        }

                        preg_match_all('/estmDataAuto\[\'T'.$trimIdx.'\'\] = \'(.*?)\'/', $html, $trimData, PREG_SET_ORDER);
                        if(!$trimData)
                        {
                            continue;
                        }
                        $a = gzuncompress(base64_decode($trimData[0][1]));
                        $b = $this->_dnwDecode($a);
                        $c = urldecode($b);
                        $d = explode('^', $c);
                        $e = str_replace('&nbsp;', ' ', $d);
                        $f = explode('!', $e[23]);
                        
                    
                        foreach($f as $row)
                        {
                            $g = explode('`', $row);
                            array_map('trim', $g);
                            if(!count($g) || count($g) <= 1)
                            {
                                continue;
                            }

                            //print_r($g);
                            $options[$brandCode][$modelCode][$trimIdx][$g[0]] = array(
                                'optionName' => $g[1] 
                                ,'optionPrice' => $g[2]
                                ,'optionCode' => $g[3]
                                ,'optionNum' => $g[4]
                                ,'optionOverlapCode' => str_replace(array('#optionDetail', '#optionDeatil'), '', $g[5])
                                ,'brandName' => $trims['brandName']
                                ,'modelName' => $trims['modelName']
                                ,'lineupName' => $trims['lineupName']
                                ,'trimName' => $trims['trimName']
                            );
                        }
                        $html->clear();
                    }
                }
            }
        }
        //$endTime = microtime(true);
        //$elapsedTimeMs = ($endTime - $startTime) * 1000;
        //echo "프로세스가 걸린 시간: " . round($elapsedTimeMs, 2) . "밀리초";
        echo "options completed \n";
        $this->_setReg('options.json', $options);
    }

    public function getColor()
    {
        $data = $this->_getReg('trims.json');
        if(!$data)
        {
            return;
        }
        $result = array();
        foreach($data as $domestic => $brands)
        {
            foreach($brands as $brandCode => $models)
            {
                foreach($models as $modelCode => $lineups)
                {
                    foreach($lineups as $lineupCode => $trims)
                    {
                        foreach($trims as $trimCode => $trim)
                        {
                            $html = $this->_htmlObject(self::BASE_URL.'service/ajax_trimsInfo.php?type=estimateTrimsColorHtml&trimsNo='.$trimCode);
                            if(!$html)
                            {
                                continue;
                            }
                            foreach(array('estimateExteriorColorList', 'estimateInteriorColorList') as $colorType)
                            {
                                $cHtml = $html->find('#'.$colorType, 0);
                                if(!$cHtml)
                                {
                                    continue;
                                }
                                foreach($cHtml->find('button.choice-color__item') as $button)
                                {
                                    $style2 = '';
                                    if($button->hasClass('choice-color--twotone'))
                                    {
                                        $style2 = $button->find('span.color', 0)->attr['style'];
                                    }
                                    $result[$domestic][$brandCode][$modelCode][$lineupCode][$trimCode][] = array(
                                        'name' => $this->_getText($button, 'span.blind')
                                        ,'code' => $button->attr['color']
                                        ,'type' => $colorType == 'estimateExteriorColorList' ? 'out' : 'in'
                                        ,'style' => str_replace(';', '', str_replace('background:', '', $button->attr['style']))
                                        ,'style2' => str_replace(';', '', str_replace('background:', '', $style2))
                                        ,'optionCode' => $button->attr['data-optioncode']
                                    );
                                }
                            }
                            $html->clear();
                        }
                    }
                }
            }
        }
        $this->_setReg('colors.json', $result);
    }
   
    public function _run()
    {
        $this->getBrands();
        $this->getModels();
        $this->getLineups();
        $this->getTrims();
        $this->getOptions();
    }

}

$danawa = new Danawa();
$danawa->_run();
?>
