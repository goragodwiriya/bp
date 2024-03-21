function initBpReport() {
  new GGraphs("bp_graph", { type: "line" });
}

function initBpRecord() {
  callClick('add_tag', function() {
      loader.location('index.php?module=bp-categories&type=tag');
  });
}