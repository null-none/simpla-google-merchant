<?php
header("Content-type: text/xml; charset=UTF-8");

require_once('api/Simpla.php');
$simpla = new Simpla();

$nsUrl    = 'http://base.google.com/ns/1.0';
$doc      = new DOMDocument('1.0', 'UTF-8');
$rootNode = $doc->appendChild($doc->createElement('rss'));
$rootNode->setAttribute('version', '2.0');
$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:g', $nsUrl);
$channelNode = $rootNode->appendChild($doc->createElement('channel'));
$channelNode->appendChild($doc->createElement('title', $simpla->settings->site_name));
$channelNode->appendChild($doc->createElement('description', $simpla->settings->company_name));
$channelNode->appendChild($doc->createElement('link', $simpla->config->root_url));


$simpla->db->query("SELECT product_id,filename FROM __images");
while ($r = $simpla->db->result()) {
    $images[$r->product_id][] = $r->filename;
}

$simpla->db->query("SET SQL_BIG_SELECTS=1");

$simpla->db->query("SELECT p.id as product_id, v.price, v.id as variant_id, p.name as product_name, v.name as variant_name, p.url, p.annotation, p.sklad, pc.category_id
                    FROM __variants v LEFT JOIN __products p ON v.product_id=p.id
                    
                    LEFT JOIN __products_categories pc ON p.id = pc.product_id AND pc.position=(SELECT MIN(position) FROM __products_categories WHERE product_id=p.id LIMIT 1)    
                    WHERE p.visible AND (v.stock >0 OR v.stock is NULL) GROUP BY v.id");

$products = $simpla->db->results();
foreach ($products as $p) {
    $price    = round($simpla->money->convert($p->price, $main_currency->id, false), 2);
    $itemNode = $channelNode->appendChild($doc->createElement('item'));
    $itemNode->appendChild($doc->createElement('title'))->appendChild($doc->createTextNode($p->product_name));
    $itemNode->appendChild($doc->createElement('description'))->appendChild($doc->createTextNode(strip_tags($p->annotation)));
    $itemNode->appendChild($doc->createElement('link'))->appendChild($doc->createTextNode($simpla->config->root_url . '/products/' . $p->url . '?variant=' . $p->variant_id));
    $itemNode->appendChild($doc->createElement('g:id'))->appendChild($doc->createTextNode($p->id));
    $itemNode->appendChild($doc->createElement('g:price'))->appendChild($doc->createTextNode($price));
    $itemNode->appendChild($doc->createElement('g:brand'))->appendChild($doc->createTextNode($price));
    $itemNode->appendChild($doc->createElement('g:condition'))->appendChild($doc->createTextNode('new'));
    
    if (isset($images[$p->product_id])) {
        $img_count = 0;
        foreach ($images[$p->product_id] as $image) {
            if ($img_count++ >= 9)
                break;
            $picture_url = $simpla->design->resize_modifier($image, 800, 600, true);
            $itemNode->appendChild($doc->createElement('g:image_link'))->appendChild($doc->createTextNode($picture_url));
        }
    }
}

echo $doc->saveXML();

?>
