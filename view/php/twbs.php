<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="Mark Otto, Jacob Thornton, and Bootstrap contributors">
    <meta name="generator" content="Hugo 0.104.2">
    <title>Grid Template · Bootstrap v5.2</title>

    <link href="./twbs_assets/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
      .bd-placeholder-img {
        font-size: 1.125rem;
        text-anchor: middle;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
      }

      @media (min-width: 768px) {
        .bd-placeholder-img-lg {
          font-size: 3.5rem;
        }
      }

      .b-example-divider {
        height: 3rem;
        background-color: rgba(0, 0, 0, .1);
        border: solid rgba(0, 0, 0, .15);
        border-width: 1px 0;
        box-shadow: inset 0 .5em 1.5em rgba(0, 0, 0, .1), inset 0 .125em .5em rgba(0, 0, 0, .15);
      }

      .b-example-vr {
        flex-shrink: 0;
        width: 1.5rem;
        height: 100vh;
      }

      .bi {
        vertical-align: -.125em;
        fill: currentColor;
      }

      .nav-scroller {
        position: relative;
        z-index: 2;
        height: 2.75rem;
        overflow-y: hidden;
      }

      .nav-scroller .nav {
        display: flex;
        flex-wrap: nowrap;
        padding-bottom: 1rem;
        margin-top: -1px;
        overflow-x: auto;
        text-align: center;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
      }
    </style>
    
    <!-- Custom styles for this template -->
    <link href="./twbs_assets/grid.css" rel="stylesheet">
  </head>
  <body class="py-4">
    <main>
      <div class="container">
        <div class="row gx-2">
          <div class="col-md-3">
            <div id="region_1" class="p-2 border bg-light "><?php if(x($page,'aside')) echo $page['aside']; ?></div>
          </div>
          <div id="region_2" class="col-md-6">
            <div class="p-2 border bg-light "><?php if(x($page,'content')) echo $page['content']; ?></div>
          </div>
          <div class="col-md-3">
            <div id="region_3" class=" p-2 border bg-light"><?php if(x($page,'right_aside')) echo $page['right_aside']; ?></div>
          </div>
        </div>
      </div>
    </main>
  </body>
</html>
