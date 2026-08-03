<?php
require("assets/php/config.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>L A N N O T E</title>
<link rel="stylesheet" href="assets/css/style.css">
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
</head>
<body>
<div class="app">
<div id="modal-settings">
<h1 class="brand">Edit Settings</h1>
  <label>Font:</label>
    <select name="font" id="font">
    <option value="Georgia, serif">Georgia, serif</option>
    <option value="Arial, sans-serif">Arial, sans-serif</option>
    <option value="sans-serif">Sans-serif</option>
    </select>
  <label>Font size:</label>
    <select name="font-size" id="font-size">
    <option value="12px">12px</option>
    <option value="14px">14px</option>
    <option value="16px">16px</option>
    <option value="18px">18px</option>
    <option value="20px">20px</option>
    </select>
  <label>Saving settings</label>
    <input type="button" value="Save" id="save-settings"/>
</div>
<div id="modal-backdrop" class="modal-backdrop"></div>
<div id="position">
  <header class="app-header">
    <h1 class="brand">L·ANNOTE </h1> 
    <div class="header-links">
      <a href="write.php" class="current">Write</a>
      <a href="index.php">View</a>
      <a href="#" onclick="showSettings();">Mod</a>
      <button id="theme-toggle" class="theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">◐</button>
      <a href="index.php?logout=1" title="Log out">⏻</a> 
    </div>
  </header>

  <div class="layout">
    <nav class="sidebar">
      <div class="sidebar-label">Notes</div>
      <ul id="note-list" class="note-list"></ul>
      <div class="sidebar-label">Categories</div>
      <ul id="category-cloud" class="category-cloud"></ul>
    </nav>

    <main class="panel">
     <a href="write.php"><img src="assets/images/pencil.png" id="pencil" /></a>
      <div class="panel-inner">
        <input type="text" id="title" class="title-input" placeholder="Untitled note" />

	<div class="toolbar">
	  <div class="tabs">
	    <button id="tab-write" class="tab active">Write</button>
	    <button id="tab-preview" class="tab">Preview</button>
	  </div>
	  <div class="save-delete-group">
	          <select id="category" class="category-select">
          <option value="">Uncategorized</option>
        </select>
	    <button id="save" class="btn-save">Save note</button>
	    <button id="delete" class="btn-save btn-delete">Delete</button>
	  </div>
	</div>

        <div class="editor-body">
          <textarea id="note" class="note-field" placeholder="Start writing in markdown…"></textarea>
          <div id="preview" class="preview-pane"></div>
        </div>
      </div>
    </main>
  </div>
</div>
</div>
<script src="assets/js/markup.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
