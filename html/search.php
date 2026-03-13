<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Elasticsearch\ClientBuilder;

// 初始化 ES 連線
$client = ClientBuilder::create()->setHosts(['elasticsearch:9200'])->build();

// 分頁與搜尋參數
$results_in_page = 10;
$pages_now = isset($_GET['page']) ? (int)$_GET['page'] : 0;
$results_num = 0;
$searching = false;

// 比對句子分類 (用於表格打勾)
function check_types($source, $target) {
    $match_types = array("BACKGROUND", "OBJECTIVES", "METHODS", "RESULTS", "CONCLUSIONS", "OTHERS");
    $match_result = array();
    foreach ($match_types as $type) {
        if (!empty($target) && isset($source[$type])) {
            $match_result[] = (strpos($source[$type], $target) !== false);
        } else { $match_result[] = false; }
    }
    return $match_result;
}

// 關鍵字黃色高亮
function high_light_keyword($keyword, $source) {
    if (empty($keyword)) return $source;
    return preg_replace('#'. preg_quote($keyword, '#') .'#i', '<span class="high-light">$0</span>', $source);
}

// 處理搜尋行為 (POST 存 Cookie，GET 讀 Cookie)
if (isset($_POST['search_type']) && isset($_POST['q'])) {
    $search_type = $_POST['search_type'];
    $search_content = $_POST['q'];
    $search_condition = array();
    foreach($search_type as $type) {
        $search_condition[] = ['match' => [$type => $search_content]];
    }
    setcookie("SearchContent", json_encode($search_content), time()+3600);
    setcookie("SearchCondition", json_encode($search_condition), time()+3600);
    setcookie("SearchType", json_encode($search_type), time()+3600);
    $searching = true; $pages_now = 0; 
} elseif (isset($_GET['page']) && isset($_COOKIE['SearchCondition'])) {
    $search_content = json_decode($_COOKIE['SearchContent'], true);
    $search_condition = json_decode($_COOKIE['SearchCondition'], true);
    $search_type = json_decode($_COOKIE['SearchType'] ?? '[]', true);
    $searching = true;
}

// 執行搜尋
if ($searching) {
    $params = [
        'index' => 'papers',
        'body'  => [
            'from' => $pages_now * $results_in_page,
            'size' => $results_in_page,
            'query' => ['bool' => ['should' => $search_condition]]
        ]
    ];
    $query = $client->search($params);
    $results = $query['hits']['hits'] ?? [];
    $results_num = $query['hits']['total']['value'] ?? 0;
    $pages_total = ceil($results_num / $results_in_page);
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Academic Search Engine</title>
    <link href="https://fonts.googleapis.com/css?family=Poppins:400,600|Noto+Sans+TC&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script defer src="https://use.fontawesome.com/releases/v5.0.6/js/all.js"></script>

    <style>
        body { width: 100%; max-width: 1000px; margin: 0 auto; background-color: #ecf0f1; font-family: 'Poppins', 'Noto Sans TC', sans-serif; padding: 0 15px; }
        
        #header-area { display: flex; align-items: center; padding: 25px 0 10px 0; cursor: pointer; }
        #logo-icon { width: 40px; height: 40px; margin-right: 15px; }
        #page-title { font-size: 24px; font-weight: bold; color: #2c3e50; margin: 0; }

        #search-box { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        
        /* 標籤選取樣式 */
        label { cursor: pointer; margin-right: 8px; }
        input[type=checkbox] { display: none; }
        input[type=checkbox]+span { display: inline-block; background-color: #ddd; padding: 5px 12px; border-radius: 6px; color: #444; transition: 0.3s; margin-bottom: 5px; }
        input[type=checkbox]:checked+span { color: #fff; background-color: #2c3e50; }

        /* 彩色標頭文字 */
        .th-b { color: #e74c3c !important; } .th-j { color: #3498db !important; }
        .th-m { color: #9b59b6 !important; } .th-r { color: #f39c12 !important; } .th-c { color: #27ae60 !important; }

        .result { margin: 20px 0; background: #fff !important; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        .post-title { color: #2980b9; font-size: 20px; text-decoration: none; font-weight: bold; }
        .post-title:hover { text-decoration: underline; }
        .high-light { background-color: #ffeb3b; font-weight: bold; padding: 0 2px; border-radius: 3px; }

        /* 分頁控制區 */
        .page-jump-box { max-width: 180px; }

        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { border: 1px solid #eee; margin-bottom: 10px; padding: 10px; border-radius: 8px; }
            td { border: none !important; position: relative; padding-left: 35% !important; text-align: left; }
            td:before { position: absolute; left: 10px; font-weight: bold; content: attr(data-label) ": "; }
        }
    </style>

    <script>
        // 跳轉功能邏輯
        function jumpToPage() {
            const input = document.getElementById('jump_input');
            const pageNum = parseInt(input.value);
            const total = <?= $pages_total ?? 1 ?>;
            if (isNaN(pageNum) || pageNum < 1 || pageNum > total) {
                alert("請輸入 1 到 " + total + " 之間的頁碼");
                return;
            }
            window.location.href = "?page=" + (pageNum - 1);
        }
        function handleEnter(e) { if (e.key === 'Enter') jumpToPage(); }
    </script>
</head>
<body>
    <div id="header-area" onclick="window.location.href='search.php'">
        <img id="logo-icon" src="elastic-283142.png" alt="Logo">
        <h1 id="page-title">Academic Search</h1>
    </div>

    <div id="search-box">
        <form action="search.php" method="POST">
            <div class="mb-2">
                <label><input type="checkbox" onClick="const c=document.getElementsByName('search_type[]');for(let i=0;i<c.length;i++)c[i].checked=this.checked"><span>All</span></label>
                <?php 
                $check_list = ["BACKGROUND", "OBJECTIVES", "METHODS", "RESULTS", "CONCLUSIONS", "OTHERS"];
                foreach($check_list as $t): 
                    $checked = (isset($search_type) && in_array($t, $search_type)) ? 'checked' : '';
                ?>
                    <label><input type="checkbox" name="search_type[]" value="<?= $t ?>" <?= $checked ?>><span><?= ucfirst(strtolower($t)) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="input-group">
                <input name="q" type="text" class="form-control" placeholder="輸入關鍵字..." value="<?= htmlspecialchars($search_content ?? '') ?>">
                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>

    <?php if(isset($results)): ?>
        <?php foreach ($results as $hit): $abstract = explode("$$$", $hit['_source']['Abstract']); ?>
            <div class="result">
                <h3 class="text-center">
                    <a class="post-title" href="https://arxiv.org/search/?query=<?= urlencode($hit['_source']['Title']) ?>&searchtype=all" target="_blank">
                        <?= $hit['_source']['Title'] ?>
                    </a>
                </h3>
                <div class="text-muted small mb-3 text-center">
                    <strong>Categories:</strong> <?= $hit['_source']['Categories'] ?> | <strong>Authors:</strong> <?= $hit['_source']['Authors'] ?>
                </div>

                <div class="mb-2 small">
                    <span class="th-b">B=Background</span> | <span class="th-j">J=Objective</span> | 
                    <span class="th-m">M=Methods</span> | <span class="th-r">R=Results</span> | <span class="th-c">C=Conclusion</span>
                </div>

                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th class="th-b">B</th><th class="th-j">J</th><th class="th-m">M</th><th class="th-r">R</th><th class="th-c">C</th><th>O</th><th>Sentence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($abstract as $line): $types = check_types($hit['_source'], $line); $lbs=["B","J","M","R","C","O"]; ?>
                            <tr>
                                <?php for($k=0;$k<6;$k++): ?><td data-label="<?= $lbs[$k] ?>"><?= $types[$k]?'✓':'' ?></td><?php endfor; ?>
                                <td data-label="Sentence"><?= high_light_keyword($search_content, $line); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <div class="d-flex flex-column align-items-center mt-5 mb-5">
            <p class="text-muted small">找到 <?= number_format($results_num) ?> 筆資料</p>
            <nav class="d-flex align-items-center flex-wrap justify-content-center">
                <ul class="pagination mb-0 me-3">
                    <li class="page-item <?= $pages_now <= 0 ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $pages_now-1 ?>">Prev</a></li>
                    <?php 
                    $start = max(0, $pages_now - 2);
                    $end = min($pages_total - 1, $pages_now + 2);
                    for($i=$start; $i<=$end; $i++): ?>
                        <li class="page-item <?= $i == $pages_now ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i+1 ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pages_now >= $pages_total - 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $pages_now+1 ?>">Next</a></li>
                </ul>

                <div class="input-group page-jump-box">
                    <input type="number" id="jump_input" class="form-control" placeholder="頁碼" onkeydown="handleEnter(event)">
                    <button class="btn btn-outline-primary" type="button" onclick="jumpToPage()">Go</button>
                </div>
            </nav>
            <span class="text-muted mt-2 small">第 <?= $pages_now + 1 ?> 頁 / 共 <?= $pages_total ?> 頁</span>
        </div>
    <?php endif; ?>
</body>
</html>
