'use strict';

angular.module('bns.viewer.workshop.document.media', [
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.viewer.workshop.document.bnsViewerWorkshopDocumentMedia
   * @kind function
   *
   * @description
   * This small directive serves as an intermediary to retrieve the actual
   * workshop document from the standard viewer media.
   *
   * @example
   * <any bns-viewer-workshop-document-media="myResource"></any>
   *
   * @returns {Object} The bnsViewerWorkshopDocumentMedia directive
   */
  .directive('bnsViewerWorkshopDocumentMedia', function (url) {
    return {
      scope: {
        resource: '=bnsViewerWorkshopDocumentMedia',
      },
      'templateUrl': url.view('viewer/workshop/document/directives/bns-viewer-workshop-document-media.html'),
      link: function (scope, element, attrs, ctrl) {
        ctrl.init(scope.resource);
      },
      controller: 'ViewerWorkshopDocumentMediaController',
    };
  })

  .controller('ViewerWorkshopDocumentMediaController', function (WorkshopRestangular, $scope, mediaLibraryConfig) {
    var ctrl = this;

    this.init = function (resource) {
      this.document = WorkshopRestangular.one('documents', resource.workshop_document_id);
      this.loadDocument();
    };

    this.loadDocument = function () {
      var params = {};
      if (angular.isDefined(mediaLibraryConfig.objectType) && angular.isDefined(mediaLibraryConfig.objectId)) {
        params.objectType = mediaLibraryConfig.objectType;
        params.objectId = mediaLibraryConfig.objectId;
      }
      this.document.get(params)
        .then(ctrl.getDocumentSuccess, ctrl.getDocumentError)
      ;
    };

    this.getDocumentSuccess = function (document) {
      $scope.document = document;
    };

    this.getDocumentError = function (result) {
      // TODO: handle this
      console.error('GET document', result);
    };
  });
