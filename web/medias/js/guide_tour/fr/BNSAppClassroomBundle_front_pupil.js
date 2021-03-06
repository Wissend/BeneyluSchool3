$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Bienvenue sur la nouvelle Beneylu School !</p><p>Avant de pouvoir accéder à tes modules, ton enseignant doit tout d'abord les <b>réactiver</b>. Sois patient, il est sûrement aussi pressé que toi de les utiliser</p>",
		id: "first",
		next: "second",
		title: "Bienvenue sur Beneylu School",
		xButton: true,
		overlay: true
	}).show();
	
	guiders.createGuider({
		attachTo:".content-footer",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.hideAll }],
		description: "<p>Sur toutes les pages de la Beneylu School, tu trouveras cette barre de navigation qui te permettra de <b>changer de module</b>.</p>",
		id: "second",
		next: "finally",
		position: 12,
		title: "Menu de navigation",
		xButton: true,
		overlay: true,
		offset: {
			top: 10,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo:".dropdown-switch-context",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Un clic sur ce bouton te donne le choix d'aller voir <b>ta classe</b> ou de <b>te déconnecter</b>.</p>",
		id: "finally",
		position: 1,
		title: "Changer de groupe",
		xButton: true,
		overlay: true,
		offset: {
			top: 30,
			left: -20
		}
	});
});
