CasRestBundle
=============

Cas Rest Bundle allows you to authenticate against a CAS server via RESTFUL services. Authored by Saud Faisal


Now go into your app/config.yml and do something as follows:

main_cas_rest:
    cas_rest_url: https://sso.myserver.com/cas/restapi/tickets
    cas_service_url: https://sso.myserver.com/cas/serviceValidate
    cas_cert: /usr/share/ca-certificates/extra/ca.crt
    cas_local:
    source_dn: 
    base_dn:
    service_url:


Please nose that you will have to adjust the path for cas_cert as it is located on your machine. 



This bundle is in middle of some cleanup and I will do my best to refactor it further. 
