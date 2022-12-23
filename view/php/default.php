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
	    <header><?php if(x($page,'header')) echo $page['header']; ?></header>
	  <div class="container-fluid">
      <div class="row gx-3 bg-white" >
          <div class="col-md-3">
            <div id="region_1" class="p-3 border border-primary h-100 rounded"><?php if(x($page,'aside')) echo $page['aside']; ?></div>
          </div>
          <div id="region_2" class="col-md-6">
            <div class="p-3 border border-primary h-100 rounded"><?php if(x($page,'content')) echo $page['content']; ?></div>
            <div class="p-3 border bg-light "><?php echo debug_print_backtrace(); ?></div>
          </div>
          <div class="col-md-3">
            <div id="region_3" class=" p-3 border border-primary h-100 rounded"><?php if(x($page,'right_aside')) echo $page['right_aside']; ?></div>
          </div>
      </div>
      <!-- end row -->
    </div>
    <!-- end container -->
    <!-- start footer -->
    <div class="container">
  <footer class="row justify-content-between align-items-center py-3 my-4 border-top">
    <p class="col-md-4 mb-0 text-muted">&copy; 2022 Company, Inc</p>
    <p class="col-md-4 mb-0 text-muted">Middle</p>

    <ul class="nav col-md-4 justify-content-end">
      <li class="nav-item"><a href="#" class="nav-link px-2 text-muted">Home</a></li>
      <li class="nav-item"><a href="#" class="nav-link px-2 text-muted">Features</a></li>
      <li class="nav-item"><a href="#" class="nav-link px-2 text-muted">Pricing</a></li>
      <li class="nav-item"><a href="#" class="nav-link px-2 text-muted">FAQs</a></li>
      <li class="nav-item"><a href="#" class="nav-link px-2 text-muted">About</a></li>
    </ul>
  </footer>
</div>

    <!-- end footer -->
  </body>
</html>
