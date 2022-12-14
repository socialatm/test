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
    <div class="container-fluid">
      <!-- start row -->
      <div class="row gx-3 bg-light mb-3" >
        <div id="region_2" class="col-md-6 mb-3">
          <div class="p-3 border border-primary rounded bg-white">
            <?php if(x($page,'content')) echo $page['content']; ?>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          <div id="region_3" class=" p-3 border border-primary rounded bg-white">
            <img src="/images/facts.png" class="img-fluid" alt="hubzilla stats">
          </div>
        </div>
      </div>
      <!-- end row -->
    </div>
    <!-- end container -->
  </body>
</html>
