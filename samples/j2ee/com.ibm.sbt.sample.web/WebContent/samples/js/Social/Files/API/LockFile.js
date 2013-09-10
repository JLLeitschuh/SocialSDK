require([ "sbt/connections/FileService", "sbt/dom", "sbt/json" ], function(FileService, dom, json) {

	var fileService = new FileService();

	fileService.lockFile("%{name=sample.fileId}").then(function(status) {
		dom.setText("json", json.jsonBeanStringify({
			status : status
		}));

	}, function(error) {
		dom.setText("json", json.jsonBeanStringify(error));
	});

});