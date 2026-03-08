<?php
// 1. 確保 ob_start 在最前面，解決 Header 報錯問題
ob_start();
require_once __DIR__ . '/../vendor/autoload.php';

// 2. 修正 Elasticsearch 連線服務名稱
$client = Elasticsearch\ClientBuilder::create()
            ->setHosts(['elasticsearch:9200']) 
            ->build();

// 基礎設定
$MAX_SEARCH = 10000;
$results_in_page = 10;
$pages_now = isset($_GET['page']) ? (int)$_GET['page'] : 0;
$results_num = 0;
$searching = false;

// 函數定義
function go_home() {
    header('Location: ' . explode('?', $_SERVER['REQUEST_URI'])[0]);
    exit;
}

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

function high_light_keyword($keyword, $source) {
    if (empty($keyword)) return $source;
    return preg_replace('#'. preg_quote($keyword) .'#i', '<span class="high-light">\\0</span>', $source);
}

// 3. 搜尋邏輯判斷
if (isset($_POST['search_type']) && isset($_POST['q'])) {
    $search_type = $_POST['search_type'];
    $search_content = $_POST['q'];
    $search_condition = array();
    foreach($search_type as $type) {
        $search_condition[] = ['match' => [$type => $search_content]];
    }
    setcookie("SearchContent", json_encode($search_content), time()+3600);
    setcookie("SearchCondition", json_encode($search_condition), time()+3600);
    $searching = true;
    $pages_now = 0; // 新搜尋從第 0 頁開始
} elseif (isset($_GET['page']) && isset($_COOKIE['SearchCondition'])) {
    if (!is_numeric($pages_now) || $pages_now < 0) go_home();
    $search_content = json_decode($_COOKIE['SearchContent'], true);
    $search_condition = json_decode($_COOKIE['SearchCondition'], true);
    $searching = true;
} else {
    setcookie("SearchContent", "", time()-3600);
    setcookie("SearchCondition", "", time()-3600);
}

// 執行搜尋
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
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Elasticsearch Search</title>
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script defer src="https://use.fontawesome.com/releases/v5.0.6/js/all.js"></script>
    
    <style>
        /* 完全還原你原始的 CSS */
        @import url('https://fonts.googleapis.com/css?family=Noto+Sans+TC&display=swap');
        body { width: 1000px; margin:0 auto; background-color: #ecf0f1; font-family: 'Poppins', 'Noto Sans TC', sans-serif;}
        
        /* Logo 區域樣式 */
        #header-area { display: flex; align-items: center; padding: 20px 20px 0 20px; }
        #logo-icon { width: 50px; height: 50px; margin-right: 15px; cursor: pointer; }
        #page-title { font-size: 24px; font-weight: bold; color: #2c3e50; margin: 0; }

        label { padding: 0; margin-right: 3px; cursor: pointer; }
        input[type=checkbox] { display: none; }
        input[type=checkbox]+span {
            display: inline-block;
            background-color: #aaa;
            padding: 3px 6px;
            border: 1px solid gray;
            color: #444;
            user-select: none;
        }
        input[type=checkbox]:checked+span { color: yellow; background-color: #444; }
        .result{
            margin: 20px;
            background: #f8f9fa !important;
            padding:20px;
            border-radius: 10px;
            box-shadow: 1px 1px 5px 5px rgba(0, 0, 0, 0.2);
        }
        .post-title{ color: black; font-size: 18px; text-decoration: none; font-weight: bold;}
        .high-light { background-color: yellow; font-weight: bold; }
        .red-text { color: red; } .blue-text { color: blue; } .purple-text { color: purple; }
        .yellow-text { color: #f39c12; } .green-text { color: green; }
        
        /* 跳轉頁面輸入框樣式 */
        .page-jump { width: 60px; display: inline-block; margin: 0 5px; text-align: center; }
    </style>

    <script>
        function toggle(source) {
            const checkboxes = document.getElementsByName('search_type[]');
            for(var i=0; i<checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }

        // 跳轉頁面函數
        function jumpToPage() {
            var pageNum = document.getElementById('page_input').value;
            var totalPages = <?= isset($pages_total) ? $pages_total : 0 ?>;
            
            // 轉為數字並驗證
            pageNum = parseInt(pageNum);
            if (isNaN(pageNum) || pageNum < 1 || pageNum > totalPages) {
                alert("請輸入有效的頁數 (1-" + totalPages + ")");
                return;
            }
            
            // 導向該頁 (內部頁碼是顯示頁碼 - 1)
            window.location.href = "?page=" + (pageNum - 1);
        }

        // 支援 Enter 鍵跳轉
        document.addEventListener('DOMContentLoaded', function() {
            var pageInput = document.getElementById('page_input');
            if (pageInput) {
                pageInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        jumpToPage();
                    }
                });
            }
        });
    </script>
</head>
<body>
    <div id="header-area">
        <img id="logo-icon" src="elastic-283142.png" alt="Elastic Logo" onclick="window.location.href='search.php'">
        <h1 id="page-title">Academic Search</h1>
    </div>

    <div id="search-box" class="navbar p-4">
        <form action="search.php" method="POST">
            <label>
                <input type="checkbox" onClick="toggle(this)">
                <span>Select All</span>
            </label>
            <label><input type="checkbox" name="search_type[]" value="BACKGROUND"><span>Background</span></label>
            <label><input type="checkbox" name="search_type[]" value="OBJECTIVES"><span>Objectives</span></label>
            <label><input type="checkbox" name="search_type[]" value="METHODS"><span>Methods</span></label>
            <label><input type="checkbox" name="search_type[]" value="RESULTS"><span>Results</span></label>
            <label><input type="checkbox" name="search_type[]" value="CONCLUSIONS"><span>Conclusions</span></label>
            <label><input type="checkbox" name="search_type[]" value="OTHERS"><span>Others</span></label>

            <div class="input-group mt-2">
                <input id="search" name="q" type="text" placeholder="Enter Keywords" class="form-control" value="<?= htmlspecialchars($search_content ?? '') ?>"/>
                <button id="submit_btn" class="btn btn-primary" type="submit">
                    <i class="fas fa-search"></i>
                </button>
                <?php if($searching): ?>
                    <button class="btn btn-secondary ms-2" type="button" onclick="window.location.href='search.php'">
                        <i class="fas fa-home"></i> Back
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if( isset($results) ): ?>
        <?php for( $i = 0 ; $i < count($results) ; $i++ ): 
            $abstract_split = explode("$$$", $results[$i]['_source']['Abstract'] );
        ?>
            <div class="result">
                <p align="center">
                    <a class="post-title" href="https://arxiv.org/search/?query=<?= urlencode($results[$i]['_source']['Title']) ?>&searchtype=all" target="_blank">
                        <?= $results[$i]['_source']['Title'] ?>
                    </a>
                </p>
                <div class="small">
                    <strong>Categories:</strong> <?= $results[$i]['_source']['Categories'] ?><br>
                    <strong>Authors:</strong> <?= $results[$i]['_source']['Authors'] ?>
                </div>
                
                <div class="result-title mt-3">
                    <span class="red-text">B=background</span> | <span class="blue-text">J=objective</span> | 
                    <span class="purple-text">M=methods</span> | <span class="yellow-text">R=results</span> | 
                    <span class="green-text">C=conclusion</span>
                </div>
                <hr>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th class="red-text">B</th><th class="blue-text">J</th><th class="purple-text">M</th>
                            <th class="yellow-text">R</th><th class="green-text">C</th><th>O</th><th>Sentence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for( $j = 0 ; $j < count( $abstract_split ) ; $j++ ): 
                            $type_search = check_types( $results[$i]['_source'], $abstract_split[$j] ); 
                        ?>
                            <tr>
                                <?php for( $k = 0 ; $k < count( $type_search ) ; $k++ ): ?>
                                    <td><?= $type_search[$k] ? '✓' : '' ?></td>
                                <?php endfor; ?>
                                <td><?= high_light_keyword($search_content, $abstract_split[$j]); ?></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endfor; ?>
    <?php endif; ?>

    <div class="col-sm-12 text-center mt-4 mb-5">
        <nav>
            <ul class="pagination justify-content-center">
                <?php if($searching && isset($pages_total) && $pages_total > 1): 
                    $range = 2; // 當前頁碼左右顯示的數量
                ?>
                    <?php if($pages_now > 0): ?>
                        <li class="page-item"><a class="page-link" href="?page=0">First</a></li>
                    <?php endif; ?>

                    <?php for($i = ($pages_now - $range); $i <= ($pages_now + $range); $i++): ?>
                        <?php if($i >= 0 && $i < $pages_total): ?>
                            <li class="page-item <?= ($i == $pages_now) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i + 1 ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if($pages_now < $pages_total - 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $pages_total - 1 ?>">Last</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>
        <p class="mt-2">
            <?php if($searching && $results_num > 0): ?>
                共 <?= number_format($results_num) ?> 項結果，這是第 <?= ($pages_now + 1) ?> 頁 / 共 <?= number_format($pages_total) ?> 頁
                <span class="ms-3">
                    跳至第 <input type="text" id="page_input" class="form-control form-control-sm page-jump" placeholder="<?= $pages_now + 1 ?>"> 頁
                    <button class="btn btn-sm btn-outline-primary" onclick="jumpToPage()">Go</button>
                </td>
            <?php endif; ?>
            <br><?= date('d-m-Y H:i:s') ?>
        </p>
    </div>
</body>
</html>