YAHOO.namespace("Wikia.Blogs");

YAHOO.Wikia.Blogs.callback = {
    success: function( oResponse ) {
        var data = YAHOO.Tools.JSONParse( oResponse.responseText );
//		location.reload();
		YAHOO.Wikia.Blogs.callback.add( data );
    },
    failure: function( oResponse ) {
    },
    timeout: 500000
};

YAHOO.Wikia.Blogs.callback.add = function( data ) {
	var li = document.createElement( "li" );
	li.innerHTML = data["text"];
	YAHOO.util.Dom.get("blog-comments-ul").appendChild( li );
	document.body.style.cursor = 'default';
};

/**
 * so far simply submit of form
 */
YAHOO.Wikia.Blogs.submit = function( event, id ) {

	var oForm = YAHOO.util.Dom.get( id );

	if( id == "blog-comm-form-select" ) {
		oForm.submit();
	}
	else {
		document.body.style.cursor = 'wait';
		YAHOO.util.Event.preventDefault( event );
		YAHOO.util.Connect.setForm( oForm, false );
		YAHOO.util.Connect.asyncRequest( "POST", wgServer + wgScriptPath + wgScript + "?action=ajax&rs=BlogComments::axPost&title=" + wgTitle.replace(" ", "_"), YAHOO.Wikia.Blogs.callback );
	}
};

YAHOO.util.Event.addListener( "blog-comm-submit-top", "click", YAHOO.Wikia.Blogs.submit, "blog-comm-form-top" );
YAHOO.util.Event.addListener( "blog-comm-submit-bottom", "click", YAHOO.Wikia.Blogs.submit, "blog-comm-form-bottom" );
YAHOO.util.Event.addListener( "blog-comm-form-select", "change", YAHOO.Wikia.Blogs.submit, "blog-comm-form-select" );
