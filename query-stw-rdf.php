<?php
  /* read input list of unique STW-Identifiers (single lines, comma separated) & query ZBW-STW (RDF) & write Aleph Sequential File */
  /* (experimental, not really efficient) */
  /* Daniel Zimmel <zimmel@coll.mpg.de> 2013 */
include_once("arc/ARC2.php");
include_once("Graphite.php");

/* read file from commandline (first argument) */
//$file = "nummern";

if (empty($argv[1])) { print "please specify an input file! \r\n"; exit;}

$file = $argv[1];
$fb = fopen($file,"r") or die("bad file");
$input = fread($fb,filesize($file));
/* delete line feeds, read into array */
$input = ereg_replace("[\r\n]","",$input);
$nummern = explode(",",$input);
fclose($fb);

/* debug */
print_r($nummern);

$sysno = "1";

global $alephseq;

foreach ($nummern as $nummer) {

  // pad zeroes for ALEPH
  $sysno = str_pad($sysno, 9, "0", STR_PAD_LEFT);

  // do some output
  print "querying id " . $nummer . "...\r\n";

$graph = new Graphite();
//$uri = "http://localhost/~zimmel/stw-part.rdf";
//$uri = "http://zbw.eu/stw";
//$uri = "http://zbw.eu/stw/descriptor/19025-3";
//$uri = "http://zbw.eu/stw/descriptor/".$argv[1];

$uri = "http://zbw.eu/stw/descriptor/".$nummer;

// load some namespaces that are not in Graphite by default (necessary?)
$graph->ns("gbv","http://purl.org/ontology/gbv/");
$graph->ns("stw","http://zbw.eu/stw/");
$graph->ns("void","http://rdfs.org/ns/void#");
$graph->ns("waiver","http://vocab.org/waiver/terms/");
$graph->ns("zbwext","http://zbw.eu/namespaces/zbw-extensions/");
$graph->load($uri);
$name = $graph->resource($uri);
// debug:
//print $graph->resource($uri)->dumpText();

/* break & continue if deprecated */
if ($graph->resource($uri)->has("owl:deprecated")) { print "    --> deprecated, skipped!\r\n"; continue; }

$alephseq .= $sysno . " 001   L \$\$a" . $nummer . "\r\n";

foreach ($graph->resource($uri)->all("skos:prefLabel") as $prefLabel) {
  $lang = $prefLabel->language();
  if ($lang == "de") {
  $alephseq .= $sysno . " 800   L \$\$a" . $prefLabel . "\r\n";
  } else {
  $alephseq .= $sysno . " 820   L \$\$a" . $prefLabel . "\r\n";
  }
}

foreach ($graph->resource($uri)->all("skos:altLabel") as $altLabel) {
  $alephseq .= $sysno . " 830   L \$\$a" . $altLabel . "\r\n";
}

foreach ($graph->resource($uri)->all("skos:related") as $related) {

  /* follow linked data */
  $reluri = new Graphite();
  $reluri->load($related); 
  foreach ($reluri->resource($related)->all("skos:prefLabel") as $relprefLabel) {
  $alephseq .= $sysno . " 860   L \$\$a" . $relprefLabel . "\r\n";
  //  print "related: ". $relprefLabel . " -> " . $relprefLabel->language() . "\r\n";
  }
}

$sysno++;

}

/* write to file */
$file = $argv[1]."-output";
$fp = fopen($file, "a") or die("bad output file");
fwrite($fp,$alephseq) or die("could not write");
fclose($fp);
?>