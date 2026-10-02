window.onload = function(){ 
	var objfa_1 = $("G1");
	var objchi_1_1 = $("G1_BP"); 
	var objchi_1_2 = $("G1_CC");
	var objchi_1_3 = $("G1_MF");
	var objfa_2 = $("G2");             
	var objchi_2_1 = $("G2_UP");
	var objchi_2_2 = $("G2_TF"); 

	ClassNode.call(G1);
	ClassNode.call(G1_BP); 
	ClassNode.call(G1_CC);
	ClassNode.call(G1_MF);
	ClassNode.call(G2);                
	ClassNode.call(G2_UP);
	ClassNode.call(G2_TF); 

	objfa_1.appendChild(objchi_1_1); 
	objfa_1.appendChild(objchi_1_2);
	objfa_1.appendChild(objchi_1_3);
	objfa_2.appendChild(objchi_2_1); 
	objfa_2.appendChild(objchi_2_2); 
}

function $(id){
    return document.getElementById(id);
}

function ClassNode(){
    var me = this;
    this._parent = null;
    this._children = new Array();
    this._nextSibling = null;
    this._prevSibling = null;
    
    this.onclick = function(){
        this.setChildsValue(me.checked);
        this._parent.checkChildsValue();
    }
    
    this.tell = function(){
        alert(me.value);
        alert(me._parent.value);
    }
    
    this.setParent = function(node){
        this._parent = node;
    }
    
    this.appendChild = function(obj){
        this._children.push(obj);
        obj.setParent(me);
    }
    
    this.checkChildsValue = function(){
        if (this._children.length == 0) {
            return;
        }

        for(var i=0;i<this._children.length;i++){
            if(this._children[i].checked==true){
                this.checked=false; //change true to false
                this._parent.checkChildsValue();
                return;
            }
        }
        this.checked = false;
        this._parent.checkChildsValue();
    }
    
    this.setChildsValue = function(val){
        for (var i=0; i<this._children.length; i++) {
            this._children[i].checked = val;
            this._children[i].setChildsValue(val);
        }
    }
}

function queryExample()
{    
	
	if (document.GSEA.organism.value == "Bpl") {
	var id = "OS493_001533-T1\nOS493_001532-T1\nOS493_001606-T1\nOS493_001605-T1\nOS493_001688-T1\nOS493_002268-T1\nOS493_002552-T1\nOS493_003505-T1\nOS493_004176-T1\nOS493_003366-T1\nOS493_007186-T1\nOS493_007807-T1\nOS493_008475-T1\nOS493_009135-T1\nOS493_013820-T1\nOS493_013977-T1\nOS493_015057-T1\nOS493_018841-T1\nOS493_019145-T1\nOS493_023595-T1\nOS493_025151-T1\nOS493_026803-T1\nOS493_028201-T1\nOS493_028550-T1\nOS493_030688-T1\nOS493_032972-T1\nOS493_033579-T1\nOS493_035385-T1\nOS493_001553-T1\nOS493_005060-T1\nOS493_007735-T1\nOS493_013112-T1\nOS493_020390-T1\nOS493_021122-T1\nOS493_025824-T1\nOS493_026407-T1\nOS493_026859-T1\nOS493_030562-T1\nOS493_032049-T1\nOS493_034108-T1\nOS493_035141-T1\nOS493_037437-T1\nOS493_039251-T1\nOS493_040222-T1\n";
	}
	document.GSEA.queryList.value=id;
}


function fileExample()
{
	if (document.Convert.species.value != "") {
		var id = "AFFX-Athal-Actin_5_r_at\n244910_s_at\n245233_at\n";
	}
	document.Convert.origList.value=id;
}

function option_showhide(id,img){
	var thisImg = document.getElementById(img);
	if(document.getElementById){
		if((document.getElementById(id).style.display == "block")){
			document.getElementById(id).style.display = 'none';
			thisImg.src="../images/plus.png";
		}else{
			document.getElementById(id).style.display = 'block';
			thisImg.src="../images/minus.png";
		}
	}else{
		if(document.layers){
			document.id.display = 
				(document.id.display == "block") ? 'none' : 'block';
		}else{
			document.all.id.style.display =
				(document.all.id.style.display == "block") ? 'none' : 'block';
		}
	}
}

function checkscript() {
if (document.GSEA.queryList.value == "" && document.GSEA.file.value == "") {
	alert ("Query box can't be empty.")
	return false
	}	
else {
	return true
	}
}

function checkquery() {
if (document.Convert.origList.value == "" && document.Convert.file1.value == "") {
	alert ("Query box can't be empty.")
	return false
	}	
else {
	return true
	}
}

function bgsuggested(){
	document.GSEA.bgList.style.display = 'none';
	document.GSEA.bgfile.style.display = 'none';
	document.getElementById("bgExa").style.display = 'none';
	document.getElementById("upbg").style.display = 'none';
}

function bgcustomized(){
	document.GSEA.bgList.style.display = 'block';
	document.GSEA.bgfile.style.display = 'block';
	document.getElementById("bgExa").style.display = 'block';
	document.getElementById("upbg").style.display = 'block';
	document.GSEA.testMethod.value = 'dhyper';
}
