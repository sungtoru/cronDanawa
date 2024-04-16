<?php
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

    private function setReg($path, $contents)
    {
        file_put_contents( __DIR__ . '/_reg/' .$path, json_encode($contents) );
    }

    private function getReg($fileName)
    {
       return json_decode(file_get_contents(__DIR__ . '/_reg/'. $fileName), true);
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

        $this->setReg('brands.json', $result);
        //return $result;
    }

    public function getModels()
    {
        $brands = $this->getReg('brands.json');
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
                        $modelInfoHtml = $this->_htmlObject(self::MODEL_INFO_URL . "=" . $modelCode);
                        $infoHtml = $modelInfoHtml->find('div.modelSummary > div.info', false);
                        $priceHtml = $infoHtml->find('div.price', false);

                        $spec = array();
                        foreach($infoHtml->find('div.spec > span') as $span)
                        {
                            $spec[] = $span->plaintext;
                        }

                        //$modelName = $this->_getText($infoHtml, 'div.title');
                        $price = $this->_getText($priceHtml, 'div.newcar > div.price_title > span.num');
                        $lentLease = $this->_getText($priceHtml, 'div.rentlease > div.price_title > span.num');
                        $release = $this->_getText($infoHtml, 'div.date');

                        $class = $li->attr['class'];
                        $statCode = 'normal';
                        if($class == 'comming' || $class == 'stock')
                        {
                            $statCode = $class;
                        }
                        $result[$origin][$brandName][] = array (
                            'brandCode' => $brand['brandCode']
                            ,'modelName' => $this->_getText($li, 'span.name')
                            ,'modelCode' => $modelCode
                            ,'statCode' => $statCode
                            ,'price' => $price
                            ,'lentLease' => $lentLease
                            ,'segment' => $spec[0]
                            ,'fules' => $spec[1]
                            ,'release' => $release
                            ,'etcSpec' => $spec[2].'|'.$spec[3]
                        );

                        $modelInfoHtml->clear();
                    }
                }
                $html->clear();
                sleep(1);
                //$this->_sleep();
            }
        }


        $this->setReg('models.json', $result);


        //return $result;
    }

    public function getLineups()
    {
        $models = $this->getReg('models.json');
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
                        $lineups[$model['brandCode']][$model['modelCode']][] = array(
                            'lineupCode' => $lineupIdx
                            ,'lineupName' => $this->_getText($lineup, 'div.name > strong')
                            ,'release' => str_replace(array('(', ')'), '', $lineupRelease)
                        );
                    }
                    $html->clear();
                }

            }
        }

        $this->setReg('lineups.json', $lineups);
    }

    public function getTrims()
    {
        $data = $this->getReg('lineups.json');
        if(!$data)
        {
            return;
        }
        $trims = array();
        foreach($data as $brandCode => $lineups)
        {
            foreach($lineups as $modelCode => $lineup)
            {
                foreach($lineup as $row)
                {
                    $html = $this->_htmlObject(self::MODEL_INFO_URL . "=" . $modelCode . '&Lineup=' . $row['lineupCode']);
                    $trimsHtml = $html->find('div.price_contents', false);
                    foreach($trimsHtml->find('dd.price_list') as $trim)
                    {
                        foreach($trim->find('ul > li') as $li)
                        {
                            $trimName = $this->_getText($li, 'div.item.name > label');
                            $engine = $this->_getText($li, 'div.item.engine');
                            $milease = $this->_getText($li, 'div.item.mileage');
                            $price = $this->_getText($li, 'span.item.price');
                            $trimHtml = $this->_getAttr($li, 'input[name="compItemCk"]', 'class');
                            $explodeTrim = explode('_', $trimHtml);
                            $trimIdx = $explodeTrim[1];
                            $trims[$brandCode][$modelCode][$row['lineupCode']][$trimIdx] = array(
                                'trimName' => $trimName
                                ,'engine' => $engine
                                ,'milease' => $milease
                                ,'price' => $price
                            );
                        }
                    }
                }

            }
        }
        $this->setReg('trims.json', $trims);
    }

    public function getOptions()
    {
        $data = $this->getReg('trims.json');
        if(!$data)
        {
            return;
        }

        print_r($data);exit;

        foreach($data as $brandCode => $lineups)
        {
            foreach($lineups as $modelCode => $lineup)
            {
                foreach($lineup as $lineupCode => $trims)
                {
                    foreach($trims as $trimIdx => $trim)
                    {
                        echo $brandCode . $modelCode . $lineupCode . $trimIdx;
                        $html = $this->_htmlObject(self::MODEL_OPTION_URL . "&Code=" . $brandCode . $modelCode . $lineupCode . $trimIdx . '&Conf=@@PG@S20@@@3@15000@CashS@@@');
                        $estimateHtml = $html->find('div.estimate-option', false);
                        print_r($estimateHtml->find('div.estimate__cont', false)->plaintext);

                        $html->clear();
                        exit;

                    }
                }
            }
        }

    }
    public function _run()
    {
        //$this->getBrands();
        //$this->getModels();
        //$this->getLineups();
        //$this->getTrims();
        //$this->getOptions();

        //$a = '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';
        $a = 'eNq1V9lyqzgQ/SXWqsljMJhARVKBQTa82TBlMJA7qRsHw9dPd0te4iS35mHyoEK7ejl9uimsh65cSTPN2MhmMSZ+YTJf2Im/n3j76MZZNO+G5RvtWeXLdCHdZCEdvsiX4iAdMUmHLaTFcDzlC7GSrurD2ir3cB+ccdIFrE2wz49G1rI+lVGf+BKagL7ok2wPTfbwJoyDXsA38bHP4FvAmqAzIitojbXlsXjhY5zxRSzzIM72RiyjEfowjmZoBshu7YZ4rhZyFm1wBDknPriHyobzRgR37NWdcJ9oBYw7el/JAO8GIMfl3Qrk2CtZcR7O3sngqzf3DvWzPqAx9RsvlsFHeQ5ySv6TzWje5TDPlP0dccgXtB++6Avcw8AXtAd0TFZgZ5CRdaVE/1VP8WsxoY8Dk89yZP7jiPOFnTZ/L3D+kWzDrN7YbVjPpQTdeFe/8GOc5UsZylHfM+8OuB9tx3/vrGWH6+ps+op2hbMu+hLWh3qd/8b1dML10izXpwTHyl892KVztL9GGiu7wTeg98p1OewIm8H0TPbmfTmULb2ZgT/p3nwohz2+e+IrZ6Jzm2YgOVf0bgN6vlcvvaiGvlVYDww2Bw7L9ie495T40Ad/MLRHwM166U3bNe8vutlqLNalUW5iWWz0e1lloi3VHm4CFl7LsG/grazeeOPOjo21dGBvnlAc2emv6iyXzcdyrWxNWAN8CcId4rGjuOCtl6Vt0HODUfu0L0OMntcAl7R+xTSO6Qw0kSHmY7gvUvvpLMQf+rorevQpvplk5/vwDjjXSh2fEu7ovpfVYCd2cByMBWkUc5zV4EcJ32YJPoUWhzCeKE6zTq+D77MmVLFLcQJYWGocdPZNPLvqXO1rv8/wYggv2YR9iBuwqYt2TQ86FoCnYJ7WIaYWbMLYUDHGDxRbKq6mm1ha0TlbzIH53BLGj+WmzqowJ8xVttcXhsJxsTYBHxziOvcUfvejkntvgeyGkjuw1Frtq7MYI97vgrDFvQTxf1lDHPftdp0fC7vnW8AYMwJtU8SOZ2zXD8eK4jiyeSYsNmnevciudErPfHIALjkQ737gPfQh+g05j3zrR8Rzd3yG8tvEWxSvyGGR85HD1HvJCnno/Da8udK8Bu+T/Q8wT33N/ypmLGp+gDgHvyKnBxPwk8FboTk4IsypPIC8G11xJwviYMojmdD5AbFZaC6/0yXbT5qPb/D0URfMbVzz6VXmW14meU02P6IO51xxirNiFBnx+8VPaZg3ddgT/7G2MrlfGLiPtZGTZAH64TZPODp/uMSJ9zYGvDLwI7/3NeL6oOVF2y/0HsT4ivI05Zkb3799vptyiKvv9jBOkjN2MLfrnANf9N/0GUvol+9ycmfo2EYsnS4cT/yOelO8mErn2P/KH1/UGn/GMnIacSDynlBYMOJf9VM6UrwiD0ErNrmxs/q3cnK7Yu02tRUcC+vhDdeqsHmvw7+QFw3mYzzH4XaT9pgbYW6CusFlK/efKqzuMTtBTsBcAvVGcVdDKIyKTnGy6HTMYR3yP8unZPiDfNPVvuJwti/xoAfYurMvxhrqoGMtiK51kC+U339Kh+l7HfiNDgrrH2MgVXHsJR/qKoqHWQyNUT+h3AHlPqiTmnqTvm/X5svzkDZQ79rFym13loFr4zZ8MHaA+7Sl+hjnXuswf6toLoKaam8/D69jifWI5mbFFbRGOTEl3C7fCNud8xXXzpoHMAeGl37Wh5QTcR+07VNq0B2U66ANS7MOH1536+pYhstjsamb54G/73D9xfsFNcc72pb7j5j3YH9vlGtzxjnWSqzzj9sX7wVi7/SZp6JrjFN9oOtiwMIF02APqi3ua2HM31mnc2JnXWphGaOuzs/oQzJ/ow/qGlwxfannrzyh9AGsS13/AE9j7fSfdPsql2ieThWHQXz9GP5Of8CfcYMx66a++lJulc+RZz/Hk/6Hu/uHKpvd5tK/t5N7UxeZKh/8FI4F+f1fYazkHQ==';
        $b = gzuncompress(base64_decode($a));
        $c = $this->dnw_decode($b);
        $d = urldecode($c);
        $e = explode('^', $d);

        print_r($e);
        /*
         * var str = dnw_deChar2(estmDataAuto["T" + valCode.trms]);
            eNq9WMmSozgQ/SWMIWL6MAez2AVREsEmF9zKuMNgsHsqplw2fP1kpsTipSv64jkQCFJCubx8mSLTf9R5LGZRws6sC86hk82YE8x5t2t5tdD9xOs2h+UnzYnTZWQLM7SFwe10GeyFEbTCYLbQGT63qR3EwpRjkMWphfNgjRHZIGthnuOdWcWaSHhN6Ai4AhgHTZjs4BIN7AnPbhPAPXRwzOCegSygNUGSkYxV+Sk78rOfcJvFBtw9fXPwu8IWHejVhqRfaoMuNrfhOUYdez1H3WCOxeE5VHrCezvEea7bsDoXaH/x4n9kLfrI7XgnzsxZ4H5dNo/Knza+X5zg2y3TG23zxhouBOjH6+2Rn/wkXYqVOKvvdJs9zvdQ/u9GX9Yol2ujj2K+wLUm+gLkh+06/RflUYvyfJavLyE++yJ1/aRx/KQ2YGz7wjvTs0gdeXdpv3ydHzYUW1d7BZ/DN5v8kFe0Z8JOnPZND/lhh/teeGzMaN1beSA9Y9q3BDu/imMTFIemklhxW54sBj9sV0sjmKeaQD/pAr91Hvymp1Z+jJqi6X117cNinu4Lvanf18v2Z8pnxYsFe0V5gvsLA3X+2q7Kluym+VZDsYgpjiZvKcYUa4gx4BJit+QrFos5yOG6m2exlnBLMsQzyke84LNcA3eLIZ4a+N5ezse14BcTfRPt4XlPe5qs3wu/AXtxlStwt8Lf6zoPOhUbzU8iuHMNciMBzLuQG4ijOmsQL5gnYaLkmDtupnIH50JeCCbHSUH5QblS1TQH1lxYa2iYK0LL4L4FjIjOT0rwaQOXv4Jn8PEOZHUn5akFuOoIS4SrHXBB6cpxPSfMSeyZct3WYYk4ccKM2Wxn/JyvMRfSrlilVXRMm41rlps12KS5f5TjXONy7aqB+6VEbMFayo9BBhh/X/OPfG1WP8Xs4yfGa8T7V/ESfWx0syPMV8WMO2hndq27zBvwQ63eu7ovfOeaUyS/QSztntMYcgfkED+Ye8xdpnlKf4hBonjNRTt3V3ylfGjC/sawt3BnNBY+yJcO6ZE0KxqDvqzq8y2jiyGfuRBXzOHEm/Mk0Hir+A0xiDiXXGdGttQZ8YxcB2tvdA7I96GrsCWyEU+oP8iAlzvWkQ7E0XBdUA/wNdy9i/RtYCjfgs6lrTgJuKq0yNYbfxLfgk7hlT8netoqv2LKQ6gdGflA8jg+u6CHZTHiU+CkTmjcqVUNGXXvYxI6u+9iAnrWgOPy2vfSFtDf1cjvCfj7oQ5XeHPz9VJ7X5+RC5VeBeoFvFmYkPsSTzHxjaFqp4W8Ed3haWpHIHO9t4/yZGqf+8CmoR4Yk9xVmEfsU35jjHp70Q+ARRfjZdJ34Hp/iTSKG/EeXIflbLv68bFZF6d8tTxlb9vy9cC/Nig/Wr82UDOQV7izMDjNb7R8PevwHavEPEzq0/vROk6wM5c4Bxn4asSloN6A7IVnaa/EatC/V/7BNcCjv7YvEdpvEa/Blb2l2kZvPvPWrLO1WW5195TpPz5RVqxKqC9/Ic9qzEGO81fvb1GDdRzeYbwM3pr/FKtigrEhltA3gE/qAP1ebt4wZn54xxt7GVtVY6xo348hN+EZchR7li44lNr2ZUH8iLwP/Ue5fYu+3tez4+shKqEPm2exWW10DWXn99UPbQO9QVRR34bvPrar9LOgd94Mejjj9fBxztuRI6i3cbxZSFzSc1HjDlwkIJ8lt1uyr9id1XuoBb57z4mQw3tZMyf901PsQZ2/secie8Gx1vJHuUT1UdVNqI1BshhqaEicN+GHp+BpB9x9i6edwhPI9oNfzSAeeZv1PXcr+417ngiohgaVUHmDPULw/9u2/8Y2xHnlXsXhvi4iJ/mKh3Ds9f1HO/Ix4VWTPI39SvOgViMGhrOIyffKl+pMwGKF0f09lnhXXya5YQ18STy6G7kT9fizfHiAQzHpdxaqNt1y/TNjVZj3OKTa2bJqZww4pF5CYq+3R/XHg514DoSagHXtys6RE70HcVY1VfZbwP0746pXkPFvZd19Rg3yjNDxbmuQOdagR/X3cR8hc8+TNejOzsZWfSXahfaYN/2RTbUX+8Fn1Vpn8Rs7wQfYOw211hv7caitAw6RV5LFox52rmzDsT7mCfaw2D88KW7Jd3Fzr/rwIa+mvQPMkf0tznGJG//Itvv+Fc53Y25wm/q5Z9Xyy13ti4mv2tCRfBVO+v3Q7s8r9L/m7oxCeO17R3VOlGcyT50Dpj5j8l9NsrvL5df4wfmn2k24re/Txh51GgNWswc9K/ai3vmhv4d/T1AT4/6sTvUR7Zb1UdkM4wfnnJ2yVdpJeX4bezwXSiy78oxcG1d15ymYdr/BNOWofsOb8rwia2Wfc9rzOOS7nGOjf4U3+BfPKUFS3/oXz7cW8YPkPnvkflXvn2YD+8aGYMIbk/OvOmM9PL/3vfHYj7jyH8ntvwPE4fJzHN/2AvXkP4jsC552lrGp5v/9H3emRA0=
	        dnw_array2(str, "setTrms");
         */
    }

    public function dnw_decode($input) {
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




}

$danawa = new Danawa();
$danawa->_run();
?>
