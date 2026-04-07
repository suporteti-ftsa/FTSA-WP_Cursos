
jQuery(document).ready(function()
{
  jQuery("input[name*='postcode']").on('keyup', function()
  {
    var cep = this.value;
    cep = cep.replace(/[\-\.\,\/]/,'');
    if(cep.length == 8)
    {
      ceppreenche(cep);
    }
  });
    setTimeout(function() {
      jQuery('#billing_postcode_field').insertBefore('#billing_address_1_field');
      jQuery('#shipping_postcode_field').insertBefore('#shipping_address_1_field');
    },50);
  
});

function ceppreenche(cep)
{
  jQuery.ajax(
    {
    url:'https://viacep.com.br/ws/'+cep+'/json/',
    crossDomain: true,
    success:function(data,status,x) {
      if (status==='success')
      {
        jQuery("input[name$='postcode']").css({'background-color':''});
        jQuery("input[name$='address_1']").val(data.logradouro);
        jQuery("input[name$='neighborhood']").val(data.bairro);
        jQuery("input[name$='city']").val(data.localidade);
        jQuery("select[name$='state']").val(data.uf);
        var lit = retornaLiteralEstado(data.uf);
        jQuery("span[id$='state-container']").text(lit);
        jQuery("span[id$='state-container']").attr('title',lit);
      }
    },
    statusCode:
    {
      404: function()
      {
        jQuery("input[name$='postcode']").css({'background-color':'#ff8957'});
        jQuery("input[name$='postcode']").val('');
      }
    }
  });
}

function retornaLiteralEstado(uf)
{
  var literal_ufs = {"AC":"Acre",
  "AL":"Alagoas",
  "AP":"Amapá",
  "AM":"Amazonas",
  "BA":"Bahia ",
  "CE":"Ceará",
  "DF":"Distrito Federal ",
  "ES":"Espírito Santo",
  "GO":"Goiás",
  "MA":"Maranhão",
  "MT":"Mato Grosso",
  "MS":"Mato Grosso do Sul",
  "MG":"Minas Gerais",
  "PA":"Pará",
  "PB":"Paraíba",
  "PR":"Paraná",
  "PE":"Pernambuco",
  "PI":"Piauí",
  "RJ":"Rio de Janeiro",
  "RN":"Rio Grande do Norte",
  "RS":"Rio Grande do Sul",
  "RO":"Rondônia",
  "RR":"Roraima",
  "SC":"Santa Catarina",
  "SP":"São Paulo",
  "SE":"Sergipe",
  "TO":"Tocantins"}
  return literal_ufs[uf];
}
