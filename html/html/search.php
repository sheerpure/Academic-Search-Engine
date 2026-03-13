<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Elasticsearch\ClientBuilder;

// 連接 Elasticsearch 服務
$client = ClientBuilder::create()
    ->setHosts(['elasticsearch:9200'])
    ->build();

// 搜尋參數設定
$MAX_SEARCH = 10000;
$results_in_page = 10;
$pages_now = isset($_GET['page']) ? (int)$_GET['page'] : 0;
$results_num = 0;
$searching = false;

// 返回首頁
function go_home() {
    header('Location: ' . explode('?', $_SERVER['REQUEST_URI'])[0]);
    exit;
}

// 比對句子屬於哪種學術類別 (Background, Methods 等)
function check_types($source, $target) {
    $match_types = array("BACKGROUND", "OBJECTIVES", "METHODS", "RESULTS", "CONCLUSIONS", "OTHERS");
    $match_result = array();
    foreach ($match_types as $type) {
        if (!empty($target) && isset($source[$type])) {
            $match_result[] = (strpos($source[$type], $target) !== false);
        } else {
            $match_result[] = false;
        }
    }
    return $match_result;
}

// 關鍵字高亮處理：修正之前的 //0 問題，改用 $0 回填匹配字串
function high_light_keyword($keyword, $source) {
    if (empty($keyword)) return $source;
    return preg_replace('#'. preg_quote($keyword, '#') .'#i', '<span class="high-light">$0</span>', $source);
}

// 處理搜尋請求 (POST 為新搜尋，GET 為分頁)
if (isset($_POST['search_type']) && isset($_POST['q'])) {
    $search_type = $_POST['search_type'];
    $search_content = $_POST['q'];
    $search_condition = array();
    foreach($search_type as $type) {
        $search_condition[] = ['match' => [$type => $search_content]];
    }
    // 存入 Cookie 方便翻頁時使用
    setcookie("SearchContent", json_encode($search_content), time()+3600);
    setcookie("SearchCondition", json_encode($search_condition), time()+3600);
    $searching = true;
    $pages_now = 0; 
} elseif (isset($_GET['page']) && isset($_COOKIE['SearchCondition'])) {
    if (!is_numeric($pages_now) || $pages_now < 0) go_home();
    $search_content = json_decode($_COOKIE['SearchContent'], true);
    $search_condition = json_decode($_COOKIE['SearchCondition'], true);
    $searching = true;
} else {
    // 清除舊搜尋紀錄
    setcookie("SearchContent", "", time()-3600);
    setcookie("SearchCondition", "", time()-3600);
}

// 執行 Elasticsearch 查詢
if ($searching) {
    $start_data_num = $pages_now * $results_in_page;
    try {
        $params = [
            'index' => 'papers',
            'body'  => [
                'from' => $start_data_num,
                'size' => $results_in_page,
                'query' => [
                    'bool' => [
                        'should' => $search_condition
                    ]
                ]
            ]
        ];
        $query = $client->search($params);

        if ($query['hits']['total']['value'] >= 1) {
            $results = $query['hits']['hits'];
            $results_num = $query['hits']['total']['value'];
            $pages_total = ceil($results_num / $results_in_page);
            if ($pages_now >= $pages_total && $pages_total > 0) go_home();
        } else {
            $pages_total = 0;
        }
    } catch (Exception $e) {
        $error_log = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Academic Search Engine</title>
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400|Noto+Sans+TC&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script defer src="https://use.fontawesome.com/releases/v5.0.6/js/all.js"></script>

    <style>
        body { 
            width: 100%; max-width: 1000px; margin: 0 auto; 
            background-color: #ecf0f1; font-family: 'Poppins', 'Noto Sans TC', sans-serif;
            padding: 0 15px;
        }
        #header-area { display: flex; align-items: center; padding: 20px 0; }
        #logo-icon { width: 50px; height: 50px; margin-right: 15px; cursor: pointer; object-fit: contain; }
        #page-title { font-size: 24px; font-weight: bold; color: #2c3e50; margin: 0; }

        /* 標籤選取樣式 */
        label { padding: 0; margin-right: 5px; cursor: pointer; }
        input[type=checkbox] { display: none; }
        input[type=checkbox]+span {
            display: inline-block; background-color: #ddd; padding: 5px 10px;
            border-radius: 5px; color: #444; transition: 0.3s; margin-bottom: 5px;
        }
        input[type=checkbox]:checked+span { color: #fff; background-color: #2c3e50; }

        .result {
            margin: 20px 0; background: #fff !important; padding: 25px;
            border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .post-title { color: #2980b9; font-size: 20px; text-decoration: none; font-weight: bold; }
        .high-light { background-color: #ffeb3b; font-weight: bold; padding: 0 2px; border-radius: 3px; }
        .red-text { color: #e74c3c; } .blue-text { color: #3498db; } .purple-text { color: #9b59b6; }
        .yellow-text { color: #f39c12; } .green-text { color: #27ae60; }

        /* RWD 響應式優化 (手機版轉卡片) */
        @media (max-width: 768px) {
            #header-area { flex-direction: column; text-align: center; }
            #logo-icon { margin-right: 0; margin-bottom: 10px; }
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { border: 1px solid #eee; margin-bottom: 15px; padding: 10px; border-radius: 8px; background: #fcfcfc; }
            td { border: none !important; position: relative; padding-left: 35% !important; text-align: left; min-height: 35px; }
            td:before { position: absolute; left: 10px; width: 30%; font-weight: bold; content: attr(data-label) ": "; }
            td[data-label="Sentence"] { padding-left: 10px !important; padding-top: 35px !important; }
            td[data-label="Sentence"]:before { top: 10px; width: 100%; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        }
    </style>

    <script>
        // 全選功能
        function toggle(source) {
            const checkboxes = document.getElementsByName('search_type[]');
            for(var i=0; i<checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
        // 分頁跳轉
        function jumpToPage() {
            var pageNum = document.getElementById('page_input').value;
            var totalPages = <?= isset($pages_total) ? $pages_total : 0 ?>;
            pageNum = parseInt(pageNum);
            if (isNaN(pageNum) || pageNum < 1 || pageNum > totalPages) {
                alert("請輸入有效的頁數 (1-" + totalPages + ")");
                return;
            }
            window.location.href = "?page=" + (pageNum - 1);
        }
    </script>
</head>
<body>
    <div id="header-area">
        <img id="logo-icon" src="elastic-283142.png" alt="Logo" onclick="window.location.href='search.php'">
        <h1 id="page-title">Academic Search</h1>
    </div>

    <div id="search-box" class="navbar p-3 bg-white rounded shadow-sm">
        <form action="search.php" method="POST" class="w-100">
            <div class="mb-2">
                <label><input type="checkbox" onClick="toggle(this)"><span>Select All</span></label>
                <label><input type="checkbox" name="search_type[]" value="BACKGROUND"><span>Background</span></label>
                <label><input type="checkbox" name="search_type[]" value="OBJECTIVES"><span>Objectives</span></label>
                <label><input type="checkbox" name="search_type[]" value="METHODS"><span>Methods</span></label>
                <label><input type="checkbox" name="search_type[]" value="RESULTS"><span>Results</span></label>
                <label><input type="checkbox" name="search_type[]" value="CONCLUSIONS"><span>Conclusions</span></label>
                <label><input type="checkbox" name="search_type[]" value="OTHERS"><span>Others</span></label>
            </div>

            <div class="input-group">
                <input id="search" name="q" type="text" placeholder="輸入關鍵字..." class="form-control" value="<?= htmlspecialchars($search_content ?? '') ?>"/>
                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                <?php if($searching): ?>
                    <button class="btn btn-outline-secondary" type="button" onclick="window.location.href='search.php'"><i class="fas fa-undo"></i></button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if( isset($results) ): ?>
        <?php foreach ($results as $hit): 
            $abstract_split = explode("$$$", $hit['_source']['Abstract']);
        ?>
            <div class="result">
                <h3 class="text-center">
                    <a class="post-title" href="https://arxiv.org/search/?query=<?= urlencode($hit['_source']['Title']) ?>&searchtype=all" target="_blank">
                        <?= $hit['_source']['Title'] ?>
                    </a>
                </h3>
                <div class="text-muted small mb-3 text-center">
                    <strong>Categories:</strong> <?= $hit['_source']['Categories'] ?> | 
                    <strong>Authors:</strong> <?= $hit['_source']['Authors'] ?>
                </div>

                <div class="mb-2 small">
                    <span class="red-text">B=Background</span> | <span class="blue-text">J=Objective</span> | 
                    <span class="purple-text">M=Methods</span> | <span class="yellow-text">R=Results</span> | 
                    <span class="green-text">C=Conclusion</span>
                </div>
                
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>B</th><th>J</th><th>M</th><th>R</th><th>C</th><th>O</th><th>Sentence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($abstract_split as $sentence): 
                            $type_search = check_types($hit['_source'], $sentence);
                            $labels = ["B", "J", "M", "R", "C", "O"];
                        ?>
                            <tr>
                                <?php for($k=0; $k<count($type_search); $k++): ?>
                                    <td data-label="<?= $labels[$k] ?>"><?= $type_search[$k] ? '✓' : '' ?></td>
                                <?php endfor; ?>
                                <td data-label="Sentence"><?= high_light_keyword($search_content, $sentence); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
