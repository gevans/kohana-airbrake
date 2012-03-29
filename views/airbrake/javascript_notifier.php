<?php defined('SYSPATH') OR die('No direct script access.'); ?>
<script type="text/javascript">
	(function(){
		var notifierJsScheme = (("https:" == document.location.protocol) ? "https://" : "http://");
		document.write(unescape("%3Cscript src='" + notifierJsScheme + "<?php echo $host ?>/javascripts/notifier.js' type='text/javascript'%3E%3C/script%3E"));
	})();
</script>

<script type="text/javascript">
	window.Airbrake = (typeof(Airbrake) == 'undefined' && typeof(Hoptoad) != 'undefined') ? Hoptoad : Airbrake
	Airbrake.setKey('<?php echo $api_key ?>');
	Airbrake.setHost('<?php echo $host ?>');
	Airbrake.setEnvironment('<?php echo $environment ?>');
	Airbrake.setErrorDefaults({ url: "<?php echo $url ?>", component: "<?php echo $controller_name ?>", action: "<?php echo $action_name ?>" });
</script>