CasRestBundle
=============

Cas Rest Bundle allows you to authenticate against a CAS server via RESTFUL services. This bundle works with the awesome FosUserBundle.


 Authored by Saud Faisal


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

Also note that as the bundle stands for now, it will first authenticate locally. In the event you are able to log on, everything is fine. 
In the event that the authentication fails, it will then go to the CAS server, and create a local DB user on successful authentication and then log you in. 

Future versions will allow you to customize this experience fully. I wrote this in a hurry so please bear with me 



This bundle is in middle of some cleanup and I will do my best to refactor it further. Also I will be adding tests in future. 

