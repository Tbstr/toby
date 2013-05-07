<!DOCTYPE html>

<html lang="de">
    <head>

        <title><?=$this->title?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />        
        <?php $this->placeHeaderInformation(); ?>

    </head>

    <body <?php $this->placeBodyAttributes()?> >
        <?=$this->content?>
    </body>
</html>