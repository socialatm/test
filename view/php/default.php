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
  <body class="py-4 mt-5 bg-light" <?php if($page['direction']) echo 'dir="rtl"' ?> >
      <?php if(x($page,'nav')) echo $page['nav']; ?>
      <?php if(x($page,'banner')) echo $page['banner']; ?>
	  <header class="container-fluid mb-3"><?php if(x($page,'header')) echo $page['header']; ?></header>
	  <div class="container-fluid">

      <!-- start row -->
      <div class="row gx-3 bg-light mb-3" >
        <div class="col-md-3">
          <div id="region_1" class="p-3 border border-primary rounded bg-white"><?php if(x($page,'aside')) echo $page['aside']; ?></div>
        </div>
        <div id="region_2" class="col-md-6">
          <div class="p-3 border border-primary rounded bg-white">
            <?php if(x($page,'content')) echo $page['content']; ?>
          </div>
        </div>
        <div class="col-md-3">
          <div id="region_3" class=" p-3 border border-primary rounded bg-white"><?php if(x($page,'right_aside')) echo $page['right_aside']; ?></div>
        </div>
      </div>
      <!-- end row -->

      <!-- start footer -->
      <div class="row gx-3 bg-light mb-3" >
        <div id="region_4" class="col-md-12">
          <div class="p-3 border border-primary rounded bg-white">
            <?php if(x($page,'footer')) echo $page['footer']; ?>
            <div class="p-3 border bg-light "><?php echo debug_print_backtrace(); ?></div>
            <div class="p-3 border bg-light "><?php echo '<pre>'; print_r($nav.help); echo '</pre>';; ?></div>

            


          </div>
        </div>
      </div>
      <!-- end footer -->
    </div>
    <!-- end container -->
  </body>
</html>
