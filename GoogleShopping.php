<?php

require_once 'simple_html_dom.php';

class GoogleShopping {
    private function fetch($url) {

        $req = curl_init();
        curl_setopt_array($req, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_USERAGENT =>
                'Opera/9.80 (X11; Linux x86_64) Presto/2.12.388 Version/12.16',
            CURLOPT_URL => $url
        ]);

        $result = curl_exec($req);
        curl_close($req);

        if($result) return $result;
        throw new Exception('Failed to retrive page.');
    }

    private function findProduct($ean) {
        $url = 'https://www.google.nl/search?hl=nl&output=search&tbm=shop&q=';

        $productsPage = $this->fetch($url . $ean);

        $dom = new simple_html_dom();
        $dom->load($productsPage);

        $links = $dom->find('h3[class=r] a');
        foreach($links as $e) {
            $pattern = '~(/shopping/product/[0-9]+)\?(\.*)~';
            if(preg_match($pattern, $e->href, $matches))
                return $matches[1];
        }
        throw new Exception('No products found.');
    }

    private function findPrices($product) {
        $url = 'https://www.google.nl/' . $product . '/online?hl=nl';
        $productPage = $this->fetch($url);

        $dom = new simple_html_dom();
        $dom->load($productPage);

        $sellers = $dom->find('tr[class=os-row]');

        $data = [ ];
        foreach($sellers as $e) {

            // <td><span><a>$seller</a></span></td>
            $seller = $e->find('td[class=os-seller-name]')[0]
                            ->children(0)->children(0)->innertext;
            $seller = trim($seller);

            // use total price column, but if it is empty
            // fallback to the base price column
            $price = $e->find('td[class=os-total-col]')[0]->innertext;
            if(empty(trim($price))) {
                $price = $e->find('td[class=os-price-col] span')[0]->innertext;
            }
            $price = preg_replace('~[^0-9,.]~','', $price);

            if(empty($price) || empty($seller))
                continue;

            $data[] = [
                "seller" => $seller,
                "price"  => $price
            ];
        }
        return $data;
    }

    public function getPrices($ean) {
        if(!preg_match('~^[0-9]{1,14}$~', $ean)) {
            throw new Exception('Invalid EAN.');
        }
        $ean = str_pad($ean, 14, '0', STR_PAD_LEFT);

        $product = $this->findProduct($ean);
        return $this->findPrices($product);
    }
}
