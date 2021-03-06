/*global describe,modules,it,expect,spyOn,jasmine*/
/*jshint maxlen:200*/
/*jshint unused:false*/
/*jslint unparam:true*/

describe('ext.wikia.adEngine.adEngine decorators', function () {
	'use strict';

	function noop() {
		return;
	}

	var mocks = {
		adConfig: {
			getDecorators: noop,
			getProviderList: noop
		},
		adDecoratorFake1: noop,
		adDecoratorFake2: noop,
		adDecoratorLegacyParamFormat: noop,
		adSlot: {},
		decoratedFillInSlot: noop,
		decoratedFillInSlotFake1: noop,
		decoratedFillInSlotFake2: noop,
		doc: {
			getElementById: function () {
				return {
					childNodes: {}
				};
			}
		},
		hooks: noop,
		lazyQueue: {
			makeQueue: noop
		},
		eventDispatcher: {},
		fillInSlot: noop,
		log: noop,
		slotRegistry: {
			add: noop,
			reset: noop
		},
		slotTweaker: {
			show: noop,
			hide: noop
		},
		slotTracker: {},
		viewabilityTracker: {
			track: noop
		}
	};

	mocks.log.levels = {};

	function getAdEngine() {
		return modules['ext.wikia.adEngine.adEngine'](
			mocks.adDecoratorLegacyParamFormat,
			mocks.eventDispatcher,
			mocks.adSlot,
			mocks.slotRegistry,
			mocks.slotTracker,
			mocks.slotTweaker,
			mocks.viewabilityTracker,
			mocks.hooks,
			mocks.doc,
			mocks.lazyQueue,
			mocks.log
		);
	}

	it('calls the legacy param format mock', function () {
		var actualDecoratedFillInSlot, adEngine;

		spyOn(mocks, 'adDecoratorLegacyParamFormat').and.returnValue(mocks.decoratedFillInSlot);
		spyOn(mocks.lazyQueue, 'makeQueue').and.callFake(function (queue, decoratedFillInSlot) {
			actualDecoratedFillInSlot = decoratedFillInSlot;
			queue.start = noop;
		});

		adEngine = getAdEngine();

		adEngine.run(mocks.adConfig, []);
		expect(mocks.adDecoratorLegacyParamFormat).toHaveBeenCalledWith(jasmine.any(Function));
		expect(actualDecoratedFillInSlot).toBe(mocks.decoratedFillInSlot);
	});

	it('calls the decorators from AdConfig and then the legacy param format mock', function () {
		var actualDecoratedFillInSlot, adEngine;

		spyOn(mocks, 'adDecoratorLegacyParamFormat').and.returnValue(mocks.decoratedFillInSlot);
		spyOn(mocks, 'adDecoratorFake1').and.returnValue(mocks.decoratedFillInSlotFake1);
		spyOn(mocks, 'adDecoratorFake2').and.returnValue(mocks.decoratedFillInSlotFake2);
		spyOn(mocks.adConfig, 'getDecorators').and.returnValue([mocks.adDecoratorFake1, mocks.adDecoratorFake2]);
		spyOn(mocks.lazyQueue, 'makeQueue').and.callFake(function (queue, decoratedFillInSlot) {
			actualDecoratedFillInSlot = decoratedFillInSlot;
			queue.start = noop;
		});

		adEngine = getAdEngine();

		adEngine.run(mocks.adConfig, []);
		expect(mocks.adDecoratorFake1).toHaveBeenCalledWith(jasmine.any(Function));
		expect(mocks.adDecoratorFake2).toHaveBeenCalledWith(mocks.decoratedFillInSlotFake1);
		expect(mocks.adDecoratorLegacyParamFormat).toHaveBeenCalledWith(mocks.decoratedFillInSlotFake2);
		expect(actualDecoratedFillInSlot).toBe(mocks.decoratedFillInSlot);
	});

	it('passes the actual fillInSlot function to the first decorator', function () {
		var originalFillInSlot, adEngine;

		spyOn(mocks, 'adDecoratorFake1').and.callFake(function (fillInSlot) {
			originalFillInSlot = fillInSlot;
			return noop;
		});
		spyOn(mocks.adConfig, 'getDecorators').and.returnValue([mocks.adDecoratorFake1]);
		spyOn(mocks.adConfig, 'getProviderList').and.returnValue([]);
		spyOn(mocks.lazyQueue, 'makeQueue').and.callFake(function (queue, decoratedFillInSlot) {
			queue.start = noop;
		});

		adEngine = getAdEngine();
		adEngine.run(mocks.adConfig, []);

		expect(originalFillInSlot).toEqual(jasmine.any(Function));
		originalFillInSlot({slotName: 'abc'});
		expect(mocks.adConfig.getProviderList).toHaveBeenCalledWith('abc');
	});
});
