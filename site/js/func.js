  function assignValuemirna()
{
	var id = "Nve-Mir-10";
	document.atidsearch.gene.value=id;
}
  function assignValueposition()
{
	var id = "NW_001834332.1:122028-122080";
	document.search.position.value=id;
}
  function assignValueNVECT()
{
	var id = "XP_048581300.1";
	document.atidsearch.gene.value=id;
}
  function assignValueNVECTposition()
{
	var id = "NC_064045.1:2300000-2400000";
	document.search.position.value=id;
}
  function assignValuePfam()
{
	var id = "PF00008";
	document.pfamsearch.pfam.value=id;
}
  function assignValuerpa1()
{
	var id = "RPACG01347.1\nRPACG01348.1\nRPACG01349.1\n";
document.atidsearch.genelist.value=id;
	document.atidsearch.species.style.display = 'block';
	document.atidsearch.species.value="Rpa";
}
  function assignValuelpe1()
{
	var id = "OS493_001533-T1\nOS493_001605-T1\nOS493_001606-T1\n";
	document.atidsearch.genelist.value=id;
	document.atidsearch.species.style.display = 'block';
	document.atidsearch.species.value="LPERT";
}
  function assignValuecsq1()
{
	var id = "CsqKR_Scaf12_54.13\nCsqKR_Scaf12_54.9\nCsqKR_Scaf12_55.15\nCsqKR_Scaf12_55.16\n";
document.atidsearch.genelist.value=id;
	document.atidsearch.species.style.display = 'block';
	document.atidsearch.species.value="Csq";
}
  function assignValuebpl1()
{
	var id = "Bpl_scaf_42969-0.35\nBpl_scaf_42969-0.36\nBpl_scaf_42969-0.37\n";
document.atidsearch.genelist.value=id;
	document.atidsearch.species.style.display = 'block';
	document.atidsearch.species.value="Bpl";
}
  function assignValuebpl2()
{
	var id = "Bpl_scaf_46488-5.31\nBpl_scaf_30474-0.8\nBpl_scaf_30185-4.5\nBpl_scaf_479-0.21\nBpl_scaf_26510-5.6\nBpl_scaf_46488-1.12\nBpl_scaf_52304-0.5\n";
document.atidsearch.genelist.value=id;
	document.atidsearch.species.style.display = 'block';
	document.atidsearch.species.value="Gigantidas platifrons";
}
  function assignValuepfam()
{
	var id = "XP_048581300.1\nXP_048581301.1\nXP_048581302.1\nXP_048581303.1\nXP_048581304.1\nXP_048581305.1\nXP_048587736.1\nXP_048579264.1\nXP_048579265.1\nXP_048579266.1\nXP_048579267.1\nXP_048579268.1\nXP_048579269.1\nXP_048580320.1\nXP_048585027.1\nXP_048585028.1\nXP_048580321.1\nXP_048585029.1\nXP_032236268.2\nXP_048580322.1\n";
	document.atidsearch.genelist.value=id;
	cnidoPickExampleSpecies("Nematostella vectensis");
}
  function assignValuego()
{
	var id = "XP_048581300.1\nXP_048581301.1\nXP_048581302.1\nXP_048581303.1\nXP_048581304.1\nXP_048581305.1\nXP_048587736.1\nXP_048579264.1\nXP_048579265.1\nXP_048579266.1\nXP_048579267.1\nXP_048579268.1\nXP_048579269.1\nXP_048580320.1\nXP_048585027.1\nXP_048585028.1\nXP_048580321.1\nXP_048585029.1\nXP_032236268.2\nXP_048580322.1\n";
	document.atidsearch.genelist.value=id;
	cnidoPickExampleSpecies("Nematostella vectensis");
}
  function assignValuekeggBpl()
{
	var id = "XP_048590157.1\nXP_048590159.1\nXP_048590162.1\nXP_048590164.1\nXP_048590169.1\nXP_048590170.1\nXP_048590171.1\nXP_048590161.1\nXP_048590083.1\nXP_048590082.1\nXP_048590080.1\nXP_048590079.1\nXP_048590078.1\nXP_048590077.1\nXP_048590066.1\nXP_048590064.1\nXP_048590063.1\nXP_048590062.1\nXP_048590061.1\nXP_048590060.1\nXP_048590059.1\nXP_048590058.1\nXP_048590057.1\nXP_048590056.1\nXP_048590055.1\nXP_048590053.1\nXP_048590051.1\nXP_048590037.1\nXP_048590035.1\nXP_048590034.1\nXP_048590033.1\nXP_048590032.1\nXP_048590031.1\nXP_048590025.1\nXP_048590021.1\nXP_048590007.1\nXP_048590003.1\nXP_048590002.1\nXP_048590001.1\nXP_048590000.1\nXP_048589999.1\nXP_048589996.1\nXP_048589995.1\nXP_048589994.1\nXP_048589993.1\nXP_048589984.1\nXP_048589961.1\nXP_048589960.1\nXP_048589959.1\nXP_048590084.1\n";
	document.atidsearch.genelist.value=id;
	/* 这一串是 Nematostella vectensis 的蛋白号，和 assignValuepfam/assignValuego
	   一样；那两个会顺带把物种下拉框切过去，这里不切，于是「示例」填进去的是
	   Nematostella 的号，下拉框却还停在默认物种上，一查就是空结果。 */
	cnidoPickExampleSpecies("Nematostella vectensis");
}

function queryExample()
{    
	var id = "Bpl_scaf_46488-5.31\nBpl_scaf_30474-0.8\nBpl_scaf_30185-4.5\nBpl_scaf_479-0.21\nBpl_scaf_26510-5.6\nBpl_scaf_46488-1.12\nBpl_scaf_52304-0.5\n";
	document.GSEA.queryList.value=id;
}
  function assignValueinterpro()
{
	var id = "XP_048581300.1\nXP_048581301.1\nXP_048581302.1\nXP_048581303.1\nXP_048581304.1\nXP_048581305.1\nXP_048587736.1\nXP_048579264.1\nXP_048579265.1\nXP_048579266.1\nXP_048579267.1\nXP_048579268.1\nXP_048579269.1\nXP_048580320.1\nXP_048585027.1\nXP_048585028.1\nXP_048580321.1\nXP_048585029.1\nXP_032236268.2\nXP_048580322.1\n";
	document.atidsearch.genelist.value=id;
	cnidoPickExampleSpecies("Nematostella vectensis");
}

  function assignValuekeyword()
{
	var id = "EGF-like";
	document.keywordsearch.keyword.value=id;
}

  function assignValuepositionBpl()
{
	var id = "Bpl_scaf_28978:28140-50166\nBpl_scaf_47430:196770-197159\nBpl_scaf_44118:45615-54674\nBpl_scaf_2398:167047-168411\nBpl_scaf_13807:75558-133037\n";
	document.positionsearch.position.value=id;
}
  function assignValueBpl()
{
	var id = "Bpl_scaf_10588-4.10\nBpl_scaf_38266-3.33\n";
document.atidsearch.genelist.value=id;
}
  function assignValueBpl_tissue()
{
	var id = "MD06G1051800\nMD02G1173600\nMD16G1152300";
document.atidsearch_tissue.genelist.value=id;
	document.atidsearch_tissue.species.style.display = 'block';
	document.atidsearch_tissue.species.value="Bpl";
}

/* =====================================================================
 * checkquery() —— 表单提交前的输入校验
 *
 * 站内 20 多个分析页都在用 onSubmit="return checkquery()"（epigenomic_data、
 * metagenomic_data、ATAC_analysis、ChIP_analysis、DHS_analysis、DNA_methylation
 * 等），但这个函数此前只定义在 GSEA/func.js 与 cytoscape/js/func.js 里，本站的
 * js/func.js 并没有它 —— 于是每次提交浏览器都会抛 ReferenceError（控制台报错，
 * 校验被跳过）。这里补一个与页面表单结构无关的通用实现。
 *
 * 返回 true 表示允许提交，false 表示拦下并提示。
 * ===================================================================== */
function checkquery() {
    // 找到触发了本次提交的那个表单。三档，从前到后：
    //   ① 被点的那个提交控件所在的表单 —— document.activeElement 就是它
    //      （鼠标点按钮、或在输入框里回车，两种情况都落在真正提交的那个表单上）；
    //   ② event.target.form —— 老写法，只在部分浏览器里成立；
    //   ③ 兜底：页面上第一个带 gene / genelist 的表单。
    // 少了 ① 的后果是实测出来的：search.php 上四个表单，只有第一个能提交，另外三个
    // 一律弹 "Please enter at least one gene ID" 且从不发出请求 —— 因为 ② 落空后
    // ③ 永远选中第一个表单（它的 gene 框是空的），后三个表单的 locus / keyword /
    // pfam 框根本没被检查过。TE.php 的 region 表单同理。
    var form = null;
    if (document.activeElement && document.activeElement.form) {
        form = document.activeElement.form;
    }
    if (!form && typeof event !== 'undefined' && event && event.target && event.target.form) {
        form = event.target.form;
    }
    if (!form) {
        var forms = document.forms;
        for (var i = 0; i < forms.length; i++) {
            if (forms[i].elements['gene'] || forms[i].elements['genelist']) { form = forms[i]; break; }
        }
        if (!form && forms.length) { form = forms[0]; }
    }
    if (!form) { return true; }

    // 基因输入框：有 genelist 的多行框优先，其次单行 gene 框
    var f = form.elements['genelist'] || form.elements['gene'] || form.elements['keyword'];
    if (f && typeof f.value === 'string') {
        if (f.value.replace(/\s/g, '') === '') {
            alert('Please enter at least one gene ID before submitting.\n'
                + 'Tip: you can paste a list separated by spaces, commas or new lines.');
            if (f.focus) { f.focus(); }
            return false;
        }
    }
    return true;
}

/* 示例链接只填基因列表、不选物种，而示例里的 XP_ 号只属于 Nematostella vectensis：
   物种下拉框停在页面默认值上，直接提交只会「无结果」。这里把物种一并选好，并同步
   上面的 Class 下拉框（否则两个控件显示不一致），最后按类群收窄显示范围。
   必须放在下面那个 x 循环之前 —— 该循环引用了未定义的 x 会抛错，它之后的语句不执行。 */
function cnidoPickExampleSpecies(latin)
{
	var form = document.atidsearch;
	if (!form || !form.species || !form.species.options) { return; }
	var i, opt = null;
	for (i = 0; i < form.species.options.length; i++) {
		if (form.species.options[i].value === latin) { opt = form.species.options[i]; break; }
	}
	if (!opt) { return; }
	var cls = opt.getAttribute('data-class');
	if (cls && form.class && form.class.options) {
		for (i = 0; i < form.class.options.length; i++) {
			if (form.class.options[i].value === cls) { form.class.selectedIndex = i; break; }
		}
	}
	if (typeof filterSpeciesByClass === 'function') { filterSpeciesByClass(); }
	form.species.value = latin;
}
