requirejs.config({
	baseUrl: "libs",
	shim: {
		bootstrap: {
			deps: ["jquery"],
		}
	},
	paths: {
		jquery: "//code.jquery.com/jquery-3.3.1.min",
		bootstrap: "/javascripts/bootstrap",
	},
});

require(["bootstrap"], function(){
});

require(["client/main"], function(main){
	main.main();
});
