import { api } from '../core/api-client.js';

const path='/api/v1/admin/settings/seo';
export const seoSettingsApi={
  read:(query='')=>api(path+(query?'?'+new URLSearchParams({q:query}):''),{kind:'admin'}),
  save:body=>api(path,{method:'PUT',body,kind:'admin'}),
};
