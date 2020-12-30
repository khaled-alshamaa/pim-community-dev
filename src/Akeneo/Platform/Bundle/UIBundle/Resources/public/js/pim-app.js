const mediator = require('oro/mediator');

/**
 * Akeneo app
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define(
    [
        'jquery',
        'underscore',
        'backbone',
        'pim/form',
        'oro/messenger',
        'pim/fetcher-registry',
        'pim/init',
        'pim/init-translator',
        'oro/init-layout',
        'pimuser/js/init-signin',
        'pim/page-title',
        'pim/date-context',
        'pim/security-context',
        'pim/user-context',
        'pim/template/app',
        'pim/template/common/flash',
        'jquery.select2.placeholder'
    ], function (
        $,
        _,
        Backbone,
        BaseForm,
        messenger,
        FetcherRegistry,
        init,
        initTranslator,
        initLayout,
        initSignin,
        pageTitle,
        DateContext,
        SecurityContext,
        UserContext,
        template,
        flashTemplate
    ) {
        return BaseForm.extend({
            tagName: 'div',
            className: 'app',
            template: _.template(template),
            flashTemplate: _.template(flashTemplate),
            events: {
              'click #overlay': 'onClickToCollapsePanel',
            },

            /**
             * {@inheritdoc}
             */
            initialize: function () {
                initLayout();
                initSignin();

                this.listenTo(mediator, 'pim-app:overlay:show', this.showOverlay);
                this.listenTo(mediator, 'pim-app:overlay:hide', this.hideOverlay);

                return BaseForm.prototype.initialize.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            configure: function () {
                return $.when(
                        FetcherRegistry.initialize(),
                        DateContext.initialize(),
                        SecurityContext.initialize(),
                        UserContext.initialize()
                    )
                    .then(initTranslator.fetch)
                    .then(function () {
                        messenger.showQueuedMessages();

                        init();

                        pageTitle.set('Akeneo PIM');

                        return BaseForm.prototype.configure.apply(this, arguments);
                    }.bind(this));
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                this.$el.html(this.template({}));

                if (!Backbone.History.started) {
                    Backbone.history.start();
                }

                return BaseForm.prototype.render.apply(this, arguments);
            },

            showOverlay: function () {
                $('#overlay').addClass('AknOverlay--show');
            },

            hideOverlay: function () {
                $('#overlay').removeClass('AknOverlay--show');
            },

            onClickToCollapsePanel: function() {
                mediator.trigger('pim-app:panel:close');
            }
        });
    });
