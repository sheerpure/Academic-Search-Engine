<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Elasticsearch\ClientBuilder;

$client = ClientBuilder::create()
    ->setHosts(['elasticsearch:9200'])
    ->build();




//設定最大搜尋筆數
$MAX_SEARCH = 10000;
//設定每頁顯示筆數
$results_in_page = 10;
//設定從第一筆開始輸出
$pages_now = 0;
$results_num = 0;

//回到未搜尋的狀態
function go_home()
	{
	header('Location: ' . $_SERVER['PHP_SELF']);
	exit;
	}

//檢查是否出現在各個類別中
function check_types( $source, $target )
	{
	//設定檢查的類別
	$match_types = array( "BACKGROUND", "OBJECTIVES", "METHODS", "RESULTS", "CONCLUSIONS", "OTHERS" );
	$match_result = array();
	for( $i = 0 ; $i < count( $match_types ) ; $i++)
		$match_result[] = count( explode($target, $source[ $match_types[$i] ]) )>1 ? true : false; //若存在該句話，則會被分割成2個以上的元素
	return $match_result;
	}

//將特定文字high light
function high_light_keyword( $keyword, $source )
	{
	return preg_replace('#'. preg_quote($keyword) .'#i', '<span class="high-light">//0</span>', $source);
	}

//確認是否為新搜尋
if( isset($_POST['search_type']) && isset($_POST['q']) )
	{
	//設定搜尋類型及內容
	$search_type = $_POST['search_type'];
	$search_content = $_POST['q'];
	
	$search_condition = array();
	foreach($search_type as $type)
		$search_condition[] = ['match' => [$type => $search_content] ];
	setcookie("SearchContent", json_encode($search_content), time()+3600, "", "", false, true);
	setcookie("SearchCondition", json_encode($search_condition), time()+3600, "", "", false, true);
	
	//紀錄正在搜尋
	$searching = true;
	}
//是否為現有搜尋
elseif( isset($_GET['page']) && isset($_COOKIE['SearchCondition']) )
	{
	//若為現有搜尋，調整啟始頁碼
	$pages_now = $_GET['page'];
	
	//排除不合理頁碼：非數字、負數、非整數
	if( !is_numeric($pages_now) || $pages_now<0 || $pages_now!=ceil($pages_now) )
		go_home();
	
	//從cookies中撈出搜尋條件
	$search_content = substr($_COOKIE['SearchContent'], 1, strlen($_COOKIE['SearchContent'])-2 );
	$search_condition = json_decode($_COOKIE['SearchCondition']);
	
	//紀錄正在搜尋
	$searching = true;
	}
//皆非則刪除搜尋條件
else
	{
	//刪除cookies(清除搜尋條件)
	setcookie("SearchContent", "", time()-3600);
	setcookie("SearchCondition", "", time()-3600);
	
	//紀錄沒在搜尋
	$searching = false;
	}

//檢查是否正在搜尋
if( $searching )
	{
	$start_data_num = $pages_now * $results_in_page;
	//排除不合理頁碼：超過搜尋上限
	if( $start_data_num >= $MAX_SEARCH )
		go_home();
	
	//搜尋並回傳結果
	$query = $client->search(
		['body' =>
			['from' => $start_data_num,
			'size' => $MAX_SEARCH,
			'query' =>
				['bool' =>
					['should' =>
						[ $search_condition ]
					]
				]
			]
		]);

	//檢查是否有搜尋結果
	if( $query['hits']['total'] >=1 )
		{
		$results = $query['hits']['hits'];
		$results_num = $query['hits']['total']['value'];
		$pages_total = ceil($results_num / $results_in_page);
		}

	//排除不合理頁碼：大於搜尋結果
	if( $pages_now >= $pages_total )
		go_home();
	}
?>

<!DOCTYPE html>
<html>
	<head>
		<div class="wrap">
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<meta http-equiv="X-UA-Compatible" content="IE=edge" />
			<meta name="author" content="Eason">
			<link href="https://fonts.googleapis.com/css?family=Poppins:300,400" rel="stylesheet" />
			<link rel="stylesheet" href="style.css">
			<script defer src="https://use.fontawesome.com/releases/v5.0.6/js/all.js"></script>
			<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
			<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
		<style>
			@import url('https://fonts.googleapis.com/css?family=Noto+Sans+TC&display=swap');
			body {
				text-align:center;
				background-color: #ecf0f1; 
				}
			label {
				padding: 0;
				margin-right: 3px;
				cursor: pointer;
			}

			/*
			input[type=checkbox] {
				display: none;
				border-radius: 10px;
			}
			
			input[type=checkbox]+span {
				position: relative;
				display: inline-block;
				color: lightgray;
				border: 1px solid black;
				border-radius: 2px;
				padding: 5px;
				background: linear-gradient(#E0E0E0, #FFFFFF);
				transform: rotate(0deg);
				top:0px;
				user-select: none;
				transition-duration: 1s;
				z-index: 99;
			}

			input[type=checkbox]:checked+span {
				background: linear-gradient(#FFFFFF, #D0D0D0);
				transform: rotate(360deg);
				color: black;
			}
			*/
			input[type=checkbox] {
            display: none;
			}
			input[type=checkbox]+span {
				display: inline-block;
				background-color: #aaa;
				padding: 3px 6px;
				border: 1px solid gray;
				color: #444;
				user-select: none; /* 防止文字被滑鼠選取反白 */
			}

			input[type=checkbox]:checked+span {
				color: yellow;
				background-color: #444;
			}
			.result{
				margin: 20px;
				background: #f8f9fa !important;
				padding:20px;
				border-radius: 10px;
				box-shadow: 1px 1px 5px 5px rgba(0, 0, 0, 0.2);
			}
			.post-title{
				color: black;
				font-size: 18px;
			}
			#searchbar{
				top:0px;
			}
			.icon{
				width: 30px;
				height:auto;
			}
			body{
				text-align:center;
				margin-top: 40vh;
				height: 100vh;
			}
			body{
				overflow: hidden;
			}
			.input-group{
				width: auto;
				margin-left:40%;
			}
		</style>
			<div id="search-box" >
				<form action="search.php" method="POST">
					<img src="elastic-283142.png" alt="elastic" class="icon">
					<label>
						<input type="checkbox"  onClick="toggle(this)" >
						<span>Select All</Select></span>
					</label>
					<label>
						<input type="checkbox" name="search_type[]" value="BACKGROUND">
						<span>Background</span>
					</label>
					<label>
						<input type="checkbox" name="search_type[]" value="OBJECTIVES"> 
						<span>Objectives</span>
					</label>
					<label>
						<input type="checkbox" name="search_type[]" value="METHODS">
						<span>Methods</span>
					</label>
					
					<label>
						<input type="checkbox" name="search_type[]" value="RESULTS">
						<span>Results</span>
					</label>
					<label>
						<input type="checkbox" name="search_type[]" value="CONCLUSIONS">
						<span>Conclusions</span>
					</label>
					<label>
						<input type="checkbox" name="search_type[]" value="OTHERS">
						<span>Others</span>
					</label>
					<div class="input-group">
						<div class="form-outline">
							<input id="search" name="q" type="text" placeholder="Enter Keywords" value=""  id="form1" class="form-control" style="position:relative;top:8px;"/>
							<label class="form-label" for="form1"></label>
						</div>
						<button id="submit_btn"  class="btn btn-primary" type="submit" value="search" style="width:50px;height:38px;position:relative;top:8px;">
						<i class="fas fa-search"></i>
						</button>
					</div>
				</form>
			</div>
		<script>
			function toggle(source) {
  				checkboxes = document.getElementsByName('search_type[]');
 			 	for(var i=0, n=checkboxes.length;i<n;i++) {
   			 	checkboxes[i].checked = source.checked;
  				}
			}
		</script>
		<script src="/choices.js">
			const searchButton = document.getElementById('submit_btn');
			const searchInput = document.getElementById('search');
			searchButton.addEventListener('click', () => {
			const inputValue = searchInput.value;
			alert(inputValue);
			});
		</script>
		</head>	
		<body>
		<?php
		if( isset($results) )
			{
			for( $i = 0 ; $i < min($results_num - $start_data_num, $results_in_page) ; $i++ )
				{
				//將Abstract每句話以"$$$"分割成矩陣中的一個元素
				$abstract_split = explode("$$$", $results[$i]['_source']['Abstract']);
		?>
				<div class="conatiner result">
						<?php
							// echo "<hr>";
						?>
						<p align="center">
							<a class="post-title" href=<?php echo "https://arxiv.org/search/?query=" . str_replace(" ", "+", $results[$i]['_source']['Title']) . "&searchtype=all&source=header" ?>>
								<?= $results[$i]['_source']['Title'] ?>
							</a>
						</p>
						<?php
							echo "Categories: ";
						?>
							<?= $results[$i]['_source']['Categories'] ?>
						<?php	
							echo '<br>';
							echo "Authors: ";
						?>
							<?= $results[$i]['_source']['Authors'] ?>
						<?php	
							echo '<br>';
							echo '<br>';
						?>	
						
						<div class="result-title">
							<span class="red-text">B=background</span>
							<span class="blue-text">J=objective</span>
							<span class="purple-text">M=methods</span>
							<span class="yellow-text">R=results</span>
							<span class="green-text">C=conclusion</span>
							<span class="black-text">O=others</span>
							
						</div>
						<?php
							echo '<br>';    
						?>
						<hr>
						<table>
						<thead>
							<tr>
								<th class="red-text">B</th>
								<th class="blue-text">J</th>
								<th class="purple-text">M</th>
								<th class="yellow-text">R</th>
								<th class="green-text">C</th>
								<th class="black-text">O</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php
							for( $j = 0 ; $j < count( $abstract_split ) ; $j++ )
								{
								$type_search = check_types( $results[$i]['_source'], $abstract_split[$j] ); 
								
								echo "<tr>";
									for( $k = 0 ; $k < count( $type_search ) ; $k++ )
										{
										echo "<td>";
										echo $type_search[$k] ? '✓' : '';
										echo "</td>";
										}
									echo "<td>";
									echo high_light_keyword( $search_content, $abstract_split[$j] );
									echo "</td>";
								echo "</tr>";
								}
							?>
						</tbody>
					</table>
					
					
				</div>
			<?php
				}
			}
			?>
		<div class="col-sm-12" style="position:relative;top:10px;">
			
			<nav aria-label="Page navigation example">
				<ul class="pagination justify-content-center" style="position:relative;top:8px;">
					<li class="page-item ">
						<?php
						if( isset($results) )
						{
						//不是第一頁才需要顯示「第一頁」及「上一頁」
						if( $pages_now > 0 )
							{
							echo '<li class="page-item"><a class="page-link" href="?page=' . 0 . '">First</a></li>';
							echo '<li class="page-item"><a class="page-link" href="?page=' . $pages_now - 1 . '" tabindex="-1">Previous</a></li>';
							}
						for( $i = max(0, $pages_now -2) ; $i < min($pages_now + 3, $pages_total) ; $i++ )
							{
							if( $i == $pages_now )
								echo '<li class="page-item active" aria-current="page"><span class="page-link" href="?page=' . $pages_now . '">'. $i+1 .' <span class="visually-hidden">(current)</span></span> </li>';
							else
								echo '<li class="page-item"><a class="page-link" href="?page=' . $i . ' ">'. $i+1 .'</a></li>';
							}
						//若不在最後一頁則顯示「下一頁」
						
						if( $pages_now < $pages_total-1 )
							{
							echo '<li class="page-item"><a class="page-link" href="?page=' . $pages_now + 1 . '">Next</a></li>';
							echo '<li class="page-item"><a class="page-link" href="?page=' . $pages_total-1 . '">Last</a></li>';
							}
						}
						?>
						
					</li>
				</ul>
			</nav>
			<br>
		</div>
		<div>
			<p>
				<?php
					if( $searching )
						echo "共" . $results_num . ($results_num == $MAX_SEARCH ? "+" : "") . "項搜尋結果，這是第" . $pages_now+1 . "頁";
				?>
			</p>
		</div>
    </body>
</html>
