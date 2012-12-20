var ModuleNavigation = function() {
};

ModuleNavigation.prototype = {
	boxes: undefined,
	switchSelector: 'input:not(:button), textarea, .filename-placeholder, .image-placeholder img',

	init: function () {
		this.boxes = $('#marketing-toolbox-form').find('.module-box');
		this.initButtons();
	},

	initButtons: function() {
		this.boxes.filter(':first').find('.nav-up').attr('disabled', 'disabled');
		this.boxes.filter(':last').find('.nav-down').attr('disabled', 'disabled');

		this.boxes.find('.nav-up').filter(':not(:disabled)').click($.proxy(this.moveUp, this));
		this.boxes.find('.nav-down').filter(':not(:disabled)').click($.proxy(this.moveDown, this));
	},

	moveUp: function(event) {
		event.preventDefault();
		var sourceBox = $(event.target).parents('.module-box');
		var destBox = $(event.target).parents('.module-box').prev();
		this.switchValues(sourceBox, destBox);
	},

	moveDown: function(event) {
		event.preventDefault();
		var sourceBox = $(event.target).parents('.module-box');
		var destBox = $(event.target).parents('.module-box').next();
		this.switchValues(sourceBox, destBox);
	},

	switchValues: function(source, dest) {
		var sourceContainers = source.find(this.switchSelector);
		var destContainers = dest.find(this.switchSelector);

		var sourceContainersLength = sourceContainers.length;
		var destContainersLength = destContainers.length;

		if (sourceContainersLength != destContainersLength) {
			throw "Switchable length not equals";
		}
		for (var i = 0; i < sourceContainersLength; i++) {
			this.switchElementValue(sourceContainers[i], destContainers[i]);
		}
	},

	switchElementValue: function(source, dest) {
		var sourceTagName = source.nodeName.toLowerCase();
		var tmp;
		if (sourceTagName != dest.nodeName.toLowerCase()) {
			throw "Switchable type not equals";
		}

		source = $(source);
		dest = $(dest);

		switch(sourceTagName) {
			case 'span':
			case 'textarea':
				tmp = source.text();
				source.text(dest.text());
				dest.text(tmp);
				break;
			case 'img':
				tmp = source.attr('src');
				source.attr('src', dest.attr('src'));
				dest.attr('src', tmp);
				break;
			default:
				tmp = source.val();
				source.val(dest.val());
				dest.val(tmp);
		}
	}
};

var moduleNavigation = new ModuleNavigation();
$(function () {
	moduleNavigation.init();
});