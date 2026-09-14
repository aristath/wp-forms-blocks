import './editor.scss';

import { init as initForm } from './form';
import { init as initFormInput } from './form-input';
import { init as initFormSubmitButton } from './form-submit-button';
import { init as initFormSubmissionNotification } from './form-submission-notification';

initForm();
initFormInput();
initFormSubmitButton();
initFormSubmissionNotification();
