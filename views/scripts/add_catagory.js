/*
script: add_catagory.js
	Controls the catagory creation interface.
*/

// UNCOMMENT FOR PROD
// (function(){
                                                       
var _template = {

	templater: new Template(),
	prepared: {},
	map: {
		'taps': 'template-taps',
		'responses': 'template-responses',
		'list.convo': 'template-list-convo',
		'list.catagories': 'template-list-catagories', 
		'suggest.group': 'template-suggest-group'
	},

	parse: function(type, data){
		var template = this.prepared[type];
		if (!template){
			template = this.map[type];
			if (!template) return '';
			template = this.prepared[type] = $(template).innerHTML.cleanup();
		}
		return this.templater.parse(template, data);
	}

};

document.onkeypress = key_event;
function key_event(evt){
     if(evt.keyCode == 13){
        _lists.saveChanges();
     }
}

var _lists = _tap.register({

	init: function(){
		_body.addEvents({
			'click:relay(.add-catagory-button)': this.doAction.toHandler(this),
            'click:relay(.change-catagory)': this.changeItem.toHandler(this),
			//'click:relay(li.catagory-item)': this.itemClick.toHandler(this),
			'click:relay(.remove-catagory)': this.itemRemove.toHandler(this),
            'click:relay(.save-button)': this.saveChanges.toHandler(this)
		});
	},

    saveChanges : function(el, e){
        var data = this.data;
        var newname = $('changedText').value;
        var scid = $('scid').value;
        var sy = $('symbol').value;
        
        
        
        new Request({
            'url': '/AJAX/group/category/add.php',
            'data': {
                type: 'editCatagory',
                name: newname,
                id: scid,
                symbol: sy
            },
            onRequest: function(){},

            onSuccess: function(json_string){
                var response = JSON.decode(json_string);
                if (response.catagorylist && $type(response.catagorylist) == 'array'){
                    list = Elements.from(_template.parse('list.catagories', response.catagorylist));
                } else {
                    list = [];
                }

                $('catagory-list').set('html','');
                $('catagory-list').adopt(list);
                
            }.bind(this)
        }).send();
    }, 
    
    changeItem: function(el, e){
        //the LI itself
        var parent = el.getParent();
        var cat = parent.get('catagoryid');
        var gid = parent.get('gid');
        var fspan = parent.getFirst();
        content = fspan.get('html');
        var data = '<input type="text" class="changedText" id="changedText" name="changedText" value="'+ content +'"><input type="hidden" id="scid" value="'+cat+'"><input type="hidden" id="group" value="'+gid+'">';
        fspan.set('html', data);
        
        //loop to delete links
        var super_parent = parent.getParent();
        var currentElement = super_parent.getFirst();
        var first = currentElement.getFirst();
        var change_button = first.getNext();
        change_button.set('html', '');
        var delete_button = change_button.getNext();
        delete_button.set('html', '');
        
        while(currentElement = currentElement.getNext()){
            var first = currentElement.getFirst();
            var change_button = first.getNext();
            change_button.set('html', '');
            var delete_button = change_button.getNext();
            delete_button.set('html', '');
        }
    },
    
    
	itemRemove: function(el, e){
		var parent = el.getParent();
        //alert("Removing: " + parent.get('catagoryid'));
		e.preventDefault();
		var remove = confirm('Are you sure you want to remove this catagory?');
		if (remove) {
		new Request({
			'url': '/AJAX/group/category/remove.php',
			'data': {
                        scid: parent.get('catagoryid')
			},
			onRequest: function(){},
			onSuccess: function(json_string){
				var response = JSON.decode(json_string);
				// ------------------------------------------------
				// METHOD 2: Trying to do it all together ---------
				if (response.catagorylist && $type(response.catagorylist) == 'array'){
					list = Elements.from(_template.parse('list.catagories', response.catagorylist));
				} else {
					list = [];
				}

				$('catagory-list').set('html','');
				$('catagory-list').adopt(list);
				
			}.bind(this)
		}).send();
		}
	},

	doAction: function(el, e){
		_create.submit(el, e);
	}
});

var _create = _tap.register({

	init: function(){
		var symbol = $('symbol');
		var data = this.data = {
			symbol: symbol.value
		};
	},

	submit: function submit(el, e){
		var data = this.data;
		var catagory = $('catagory-add-input').value;
        $('catagory-add-input').set('value', ''); 
		new Request({
			'url': '/AJAX/group/category/add.php',
			'data': {
				type: 'catagory',
				name: catagory,
				symbol: data.symbol,
			},
			onRequest: function(){
	},

			onSuccess: function(json_string){
				var response = JSON.decode(json_string);
				
				// ------------------------------------------------
				// METHOD 2: Trying to do it all together ---------
				if (response.catagorylist && $type(response.catagorylist) == 'array'){
					list = Elements.from(_template.parse('list.catagories', response.catagorylist));
				} else {
					list = [];
				}

				$('catagory-list').set('html','');
				$('catagory-list').adopt(list);
				
			}.bind(this)
		}).send();
	}

});

// })();
