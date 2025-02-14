import {AutoNumber, FamilyProperty, FreeText, SimpleSelectProperty} from './properties';

enum PROPERTY_NAMES {
  AUTO_NUMBER = 'auto_number',
  FREE_TEXT = 'free_text',
  FAMILY = 'family',
  SIMPLE_SELECT = 'simple_select',
}

const ALLOWED_PROPERTY_NAMES = [
  PROPERTY_NAMES.FREE_TEXT,
  PROPERTY_NAMES.AUTO_NUMBER,
  PROPERTY_NAMES.FAMILY,
  PROPERTY_NAMES.SIMPLE_SELECT,
];

type Property = {type: PROPERTY_NAMES} & (AutoNumber | FreeText | FamilyProperty | SimpleSelectProperty);

type Structure = Property[];

export {ALLOWED_PROPERTY_NAMES, PROPERTY_NAMES};
export type {Structure, Property};
