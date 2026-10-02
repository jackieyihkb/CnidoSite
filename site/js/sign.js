$(document).ready(function(){  
  var urlstr = location.href.split('?');
  var urlstatus=false;
  $("#form a").each(function () {
    if ((urlstr[0] + '/').indexOf($(this).attr('href')) > -1 && $(this).attr('href')!='') {
		
      $(this).addClass('cur'); 

	  urlstatus = true;
    } 
	//else {
    //  $(this).removeClass('cur');
    //}
  });
  //if (!urlstatus) {$("#form a").eq(0).addClass('cur'); }

  
  switch (urlstr[0]) 
	{
		case 'http://10.2.42.7/MCPNet/index.html':$("#form a").eq(0).addClass('cur'); 
		break;
		case 'http://10.2.42.7/MCPNet/cytoscape/network.list.php': $("#form a").eq(2).addClass('cur'); 
		break; 
		case 'http://10.2.42.7/MCPNet/cytoscape/tissue_specific.php':$("#form a").eq(2).addClass('cur');
		break; 
		case 'http://10.2.42.7/MCPNet/cytoscape/treat_change.php':$("#form a").eq(2).addClass('cur');
		break; 
		case 'http://10.2.42.7/MCPNet/cytoscape/network.compare.php':$("#form a").eq(3).addClass('cur');
		break; 
		case 'http://10.2.42.7/MCPNet/cytoscape/tissue_specific.php':$("#form a").eq(3).addClass('cur');
		break; 
		case 'http://10.2.42.7/MCPNet/module/module_CF.php':$("#form a").eq(4).addClass('cur');
		break; 
		case 'http://10.2.42.7/MCPNet/module/module_domain.php':$("#form a").eq(4).addClass('cur');
		break; 		
		case 'http://10.2.42.7/MCPNet/search_result.php':$("#form a").eq(1).addClass('cur');
		break; 		
		case 'http://10.2.42.7/cgi-bin/motif/location.detail.cgi':$("#form a").eq(6).addClass('cur');
		break; 
		case 'http://10.2.42.7/cgi-bin/motif/custom_motif_result.cgi':$("#form a").eq(6).addClass('cur');
		break; 
		case 'http://10.2.42.7/cgi-bin/motif/promoter_fa_scan.cgi':$("#form a").eq(6).addClass('cur');
		break; 
		case 'http://10.2.42.7/MCPNet/GSEA/plantGSEA.php':$("#form a").eq(9).addClass('cur');
		break; 
		
		
	}
  
  
  });  
  

