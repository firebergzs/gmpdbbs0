<?php
require_once("./manage_database.php");
$limit = 0;
$hash = "a534610eb2c0fdc518a817476811870a09ed82ab";
$db = new Db();

function write_log($db, $title, $msg){
	$logsql = "insert into bakusai_log (title, msg) values (\"${title}\", \"${msg}\");";
	$db->execute($logsql);
}

write_log($db, "information_opened", $_SERVER["HTTP_USER_AGENT"]);

$authenticated = false;
$cookie_hash = $_COOKIE["auth"];
if ($db->query("select sessionhash from bakusai_session where sessionhash = \"${cookie_hash}\"")){
	$authenticated = true;
	write_log($db, "authentication_success", "session authenticated");
} else {
	if (isset($_GET["key"])){
		if (sha1($_GET["key"]) != $hash) {
			write_log($db, "authentication_fail", "authentication failed by wrong key");
			die("invalid key");
		}
	} else {
		write_log($db, "authentication_fail", "authentication failed by no key");
		die("forbidden.");
	}
	$sessionhash = sha1(date("ymdhis").$_SERVER["REMOTE_ADDR"]);
	setcookie("auth", $sessionhash, time() + 60*60*24*365);
	$db->execute("insert into bakusai_session (sessionhash) values (\"${sessionhash}\");");
	write_log($db, "authentication_success", "created session");
}

if (isset($_GET["limit"])){
	$limit_string = $_GET["limit"];
	try {
		$limit = (int)$limit_string;
	} catch (Exception $e) {
		var_dump($e);
		die ("invalid limit");	
	}
	if (!is_int($limit)) die ("invalid limit");
}

$option = "";
if (isset($_GET["place"])){
	$place = $_GET["place"];
	$place = str_replace(";", "", $place);
	$option = "where place like \"%${place}%\" ";
}

$sql = "select place, bbsurl, bbstitle, postat, message, h_w, height, weight, age from bakusai ${option}order by postat desc";
// echo $sql;
$load_all_data = "";
if (is_int($limit) && $limit > 0){
	$sql .= " limit ${limit}";
	$all_count = $db->query("select count(*) as cnt from bakusai;")[0]["cnt"];
	$load_all_data = "<a href=\"./\">load all data (${all_count} records)</a> <- will take several minutes...😅";
}
$articles = $db->query($sql);
$sql = str_replace("\"", "\\\"", $sql);
write_log($db, "execute_query", $sql);
$count = count($articles);
$count_str = number_format($count);
preg_match("/\d{4}-\d{2}-\d{2}/", $articles[$count-1]["postat"], $match_date);
$first = $match_date[0];

$sql = "select place, count(*) as count from bakusai group by place order by count desc";
$places = $db->query($sql);

?>
<!DOCTYPE html>
<html>
<head>
	<title>Bakusai Database</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs4-4.1.1/jq-3.3.1/dt-1.10.23/fh-3.1.7/r-2.2.7/datatables.min.css"/>
 	<script type="text/javascript" src="https://cdn.datatables.net/v/bs4-4.1.1/jq-3.3.1/dt-1.10.23/fh-3.1.7/r-2.2.7/datatables.min.js"></script>
 	<script>
 		var table;
		$(document).ready(function () {
			// $("#dataTable thead tr").clone(true).appendTo( '#dataTable thead' );
			// $('#dataTable thead tr:eq(1) th').each( function (i) {
		 //        var title = $(this).text();
		 //        $(this).html( '<input type="text" placeholder="Filter '+title+'" />' );
		 //        $( 'input', this ).on( 'keyup change', function () {
		 //            if ( table.column(i).search() !== this.value ) {
		 //                table
		 //                    .column(i)
		 //                    .search( this.value )
		 //                    .draw();
			//         }
			//     });
		 //    });
			table = $("#dataTable").DataTable({
				"lengthMenu": [[25, 50, 75, -1], [25, 50, 75, "All"]],
				"pageLength": 100,
				"dom": "Bfrtip",
				"order": [[ 0, "desc" ]],
				"fixedHeader": true,
				"regex": true,
				"orderCellsTop": true,
				"drawCallback": function( settings ) {
					$('[data-toggle="popover"]').popover({html:true, placement:'bottom', selector: true});
					// $("span").popover("hide");
					document.getElementById("description").innerText = document.getElementById("dataTable_info").innerText;
			    }
			});
		});
		function filter_gmpd(obj){
			if (obj.innerText == "gmpd only"){
				table.columns(7).search("debu|gachimuchi", true).draw();
				obj.innerText = "clear filter";
				obj.classList.remove("btn-primary");
			} else {
				table.columns(7).search(".", true).draw();
				obj.innerText = "gmpd only";
				obj.classList.add("btn-primary");
			}
		}
		function set_filter(obj){
			var form = document.getElementsByTagName("input")[0];
			if (form.value == obj.innerText){
				form.value = "";
					table.search("").draw();
			} else {
				form.value = obj.innerText;
				table.search(obj.innerText).draw();
			}
			hide_popover();
		}
		function hide_popover(){
			$("span").popover("hide");
		}
		function switch_places(obj){
			if (obj.innerText == "close places"){
				// document.getElementById("places").style = "display: none;"
				obj.innerText = "show places";
				obj.classList.add("btn-primary");
			} else {
				// document.getElementById("places").style = "display: inline;"
				obj.innerText = "close places";
				obj.classList.remove("btn-primary");
			}
			$("#places").slideToggle();
		}
	</script>

</head>
<body style="background-color: #00B900; padding: 12px; font-size: 8pt; text-align: center;">
	<h3 style="text-shadow: 1px 2px 3px;"><a href="./?limit=1000" style="color: #fff;">Bakusai Database</a></h3>
	<span style="color: #ddd;">now <?=$count_str?> messages since <?=$first?></span><br>
	<?=$load_all_data?><br>
	<a href="https://gmpddev.gndf.net/public/dashboard/184c8327-1068-41b1-b944-227576181f84" target="_blank">Analysis Dashboard</a><br>
	<span id="description"></span><br>
	<button class="button btn-primary" style="border-radius: 8px; margin: 12px; padding: 4px;" onclick="filter_gmpd(this);">gmpd only</button>
	<button class="button btn-primary" style="border-radius: 8px; margin: 12px; padding: 4px;" onclick="switch_places(this);">show places</button>

	<div id="places" style="display: none;">
		<table class="table table-striped table-dark" style="border-radius: 12px;">
			<thead style="background-color: orange;">
				<tr>
					<th>Place</th>
					<th>Count</th>
				</tr>
			</thead>
			<tbody>
<?php
foreach($places as $row){
	$place = $row["place"];
	$count = $row["count"];
?>
				<tr>
					<td><a href="./?place=<?=$place?>&limit=1000"><?=$place?></a></td>
					<td><?=$count?></td>
				</tr>
<?php
}
?>
			</tbody>
		</table>
	</div>


	<table class="table table-responsive table-striped table-dark" id="dataTable" width="100%" cellspacing="0" style="border-radius: 12px;">
		<thead style="background-color: royalblue;">
			<tr>
				<th>Datetime</th>
				<th>Place</th>
				<th>Message</th>
				<th>Height-Weight</th>
				<th>Height</th>
				<th>Weight</th>
				<th>Age</th>
				<th>Tag</th>
			</tr>
		</thead>
		<tbody>
<?php
foreach($articles as $row){
	$datetime = $row["postat"];
	$place = $row["place"];
	$bbsurl = $row["bbsurl"];
	$msg = $row["message"];
	$h_w = $row["h_w"];
	$height = $row["height"];
	$weight = $row["weight"];
	$age = $row["age"];
	$title = $row["bbstitle"];
	$addstyle = "";
?>
<?php if ($h_w <= 70) : ?>
			<tr style="color: lightgreen; font-weight: bold;">
<?php elseif ($h_w <= 80) : ?>
			<tr style="color: cyan;">
<?php elseif ($h_w <= 90) : ?>
			<tr style="color: white;">
<?php else : ?>
			<tr style="color: gray;">
<?php endif; ?>
				<td><?=$datetime?></td>
				<td><span onclick="//hide_popover();" style="text-decoration: underline; cursor : pointer;" data-toggle="popover" data-content="<a href='<?=$bbsurl?>' target='_blank'><?=$title?></a><hr><a href='#' onclick='set_filter(this);'><?=$place?></a>でフィルタする<div style='text-align: right; cursor : pointer;' onclick='hide_popover();'>☒</div>"><?=$place?></span></td>
				<td><?=$msg?></td>
				<td><?=$h_w?></td>
				<td><?=$height?></td>
				<td><?=$weight?></td>
				<td><?=$age?></td>
<?php if ($h_w <= 70) : ?>
			<td>debu</td>
<?php elseif ($h_w <= 80) : ?>
			<td>gachimuchi</td>
<?php elseif ($h_w <= 90) : ?>
			<td>normal</td>
<?php else : ?>
			<td>gari</td>
<?php endif; ?>
			</tr>
<?php
}
?>
		</tbody>
	</table>
</body>
</html>