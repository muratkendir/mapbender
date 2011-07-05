(function($) {

$.widget("mapbender.mb{{ widgetName }}", {
    options: {},

    elementUrl: null,

    _create: function() {
        var self = this;
        var me = $(this.element);
        this.elementUrl = Mapbender.configuration.elementPath + me.attr('id') + '/';
    },

    destroy: function() {
        $.Widget.prototype.destroy.call(this);
    }
});

})(jQuery);

