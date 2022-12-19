<!doctype html>
<html lang="en" prefix="og: http://ogp.me/ns#">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <title><?php if(x($page,'title')) echo $page['title'] ?></title>
    <script>var baseurl="<?php echo z_root() ?>";</script>
    <?php if(x($page,'htmlhead')) echo $page['htmlhead'] ?>

  </head>
  <body class="py-4" <?php if($page['direction']) echo 'dir="rtl"' ?> >
      <?php if(x($page,'nav')) echo $page['nav']; ?>
      <?php if(x($page,'banner')) echo $page['banner']; ?>
	    <header><?php if(x($page,'header')) echo $page['header']; ?></header>
	  <main class="mt-5">
      <div class="container-fluid">
        <div class="row gx-2">
          <div class="col-md-3">
            <div id="region_1" class="p-2 border bg-light "><?php if(x($page,'aside')) echo $page['aside']; ?></div>
          </div>
          <div id="region_2" class="col-md-6">
            <div class="p-2 border bg-light "><?php if(x($page,'content')) echo $page['content']; ?></div>
            <div class="p-2 border bg-light "><?php echo debug_print_backtrace(); ?></div>
          </div>
          <div class="col-md-3">
            <div id="region_3" class=" p-2 border bg-light"><?php if(x($page,'right_aside')) echo $page['right_aside']; ?></div>
          </div>
        </div>
      </div>
    </main>
  </body>
</html>
