<?php
/**
 *   * Name: full
 *   * Description: A single column full width layout with the hubzilla navbar
 *   * Version: 1
 *   * Author: None
 *   * Maintainer: None
 *   * ContentRegion: content, region_1
 */
?>
<!DOCTYPE html >
<html prefix="og: http://ogp.me/ns#">
<head>
  <title><?php if(x($page,'title')) echo $page['title'] ?></title>
  <script>var baseurl="<?php echo z_root() ?>";</script>
  <?php if(x($page,'htmlhead')) echo $page['htmlhead'] ?>
</head>
<body <?php if($page['direction']) echo 'dir="rtl"' ?> >
	<?php if(x($page,'banner')) echo $page['banner']; ?>
	<header><?php if(x($page,'header')) echo $page['header']; ?></header>
	<?php if(x($page,'nav')) echo $page['nav']; ?>
	<section id="region_1">
		<?php if(x($page,'content')) echo $page['content']; ?>
		<div id="page-footer"></div>
	</section>
</body>
</html>
