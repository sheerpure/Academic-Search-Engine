<?php
// 1. 解除 PHP 記憶體限制 (設為 -1 代表不限制，或是設為 512M)
ini_set('memory_limit', '512M');
set_time_limit(0); // 避免匯入太久導致逾時

require_once __DIR__ . '/../vendor/autoload.php';

$client = Elasticsearch\ClientBuilder::create()
            ->setHosts(['elasticsearch:9200']) 
            ->build();

$jsonFile = __DIR__ . '/../data/data.json'; 

if (!file_exists($jsonFile)) {
    die("找不到 JSON 檔案！");
}

// 2. 讀取資料
$data = json_decode(file_get_contents($jsonFile), true);

if ($data === null) {
    die("JSON 格式錯誤或檔案太大無法解析。");
}

$total = count($data);
echo "準備匯入 $total 筆資料...<br>";

$params = ['body' => []];
foreach ($data as $i => $item) {
    $params['body'][] = ['index' => ['_index' => 'papers']];
    $params['body'][] = $item;

    if ($i % 500 == 0 && $i > 0) {
        $client->bulk($params);
        $params = ['body' => []];
        echo "已完成 $i 筆...<br>";
        ob_flush(); // 強制輸出到瀏覽器
        flush();
    }
}

if (!empty($params['body'])) {
    $client->bulk($params);
}

echo "<b>匯入完成！現在可以去搜尋了。</b>";