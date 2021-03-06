require(["sbt/dom", "sbt/config" ], function(dom, config) {
    var endpoint = config.findEndpoint("connections");
    
    var options = { 
        method : "GET", 
        handleAs : "text",
        query : {
        	userid : "%{name=sample.id1|helpSnippetId=Social_Profiles_Get_Profile}"
        }
    };
        
    endpoint.request("/profiles/atom/profile.do", options).then(
        function(response) {
        	dom.setText("content", response);
        },
        function(error){
        	dom.setText("content", error);
        }
    );
});