import {useCallback, useContext, useEffect, useState} from 'react';
import {saveEditCategoryForm} from '../../infrastructure/savers';
import {NotificationLevel, useNotify, useTranslate} from '@akeneo-pim-community/shared';
import {EditCategoryForm, useCategory} from './useCategory';
import {EditCategoryContext} from '../../components';

// @todo Add unit tests
const useEditCategoryForm = (categoryId: number) => {
  const notify = useNotify();
  const translate = useTranslate();
  const {categoryData, status: categoryLoadingStatus, load: loadCategory} = useCategory(categoryId);
  const [originalFormData, setOriginalFormData] = useState<EditCategoryForm | null>(null);
  const [editedFormData, setEditedFormData] = useState<EditCategoryForm | null>(null);
  const [thereAreUnsavedChanges, setThereAreUnsavedChanges] = useState<boolean>(false);
  const {setCanLeavePage} = useContext(EditCategoryContext);

  const haveLabelsBeenChanged = useCallback((): boolean => {
    if (originalFormData === null || editedFormData === null) {
      return false;
    }

    for (const [locale, changedLabel] of Object.entries(editedFormData.label)) {
      if (!originalFormData.label.hasOwnProperty(locale) && changedLabel.value === '') {
        return false;
      }
      if (
        !originalFormData.label.hasOwnProperty(locale) ||
        originalFormData.label[locale].value !== changedLabel.value
      ) {
        return true;
      }
    }

    return false;
  }, [originalFormData, editedFormData]);

  const havePermissionsBeenChanged = useCallback((): boolean => {
    if (
      originalFormData === null ||
      editedFormData === null ||
      !originalFormData.permissions ||
      !editedFormData.permissions
    ) {
      return false;
    }

    return (
      JSON.stringify(originalFormData.permissions.view.value) !=
        JSON.stringify(editedFormData.permissions.view.value) ||
      JSON.stringify(originalFormData.permissions.edit.value) !=
        JSON.stringify(editedFormData.permissions.edit.value) ||
      JSON.stringify(originalFormData.permissions.own.value) != JSON.stringify(editedFormData.permissions.own.value)
    );
  }, [originalFormData, editedFormData]);

  const onChangeCategoryLabel = (locale: string, label: string) => {
    if (editedFormData === null || !editedFormData.label.hasOwnProperty(locale)) {
      return;
    }

    const editedLabel = {...editedFormData.label[locale], value: label};
    setEditedFormData({...editedFormData, label: {...editedFormData.label, [locale]: editedLabel}});
  };

  const onChangePermissions = (type: string, values: any) => {
    if (editedFormData === null || !editedFormData.permissions) {
      return;
    }

    switch (type) {
      case 'view':
        setEditedFormData({
          ...editedFormData,
          permissions: {...editedFormData.permissions, view: {...editedFormData.permissions.view, value: values}},
        });
        break;
      case 'edit':
        setEditedFormData({
          ...editedFormData,
          permissions: {...editedFormData.permissions, edit: {...editedFormData.permissions.edit, value: values}},
        });
        break;
      case 'edit':
        setEditedFormData({
          ...editedFormData,
          permissions: {...editedFormData.permissions, own: {...editedFormData.permissions.own, value: values}},
        });
        break;
    }
  };

  const onChangeApplyPermissionsOnChilren = (value: any) => {
    if (editedFormData === null || !editedFormData.permissions) {
      return;
    }

    const editedApplyOnChildren = {...editedFormData.permissions.apply_on_children, value: value === true ? '1' : '0'};
    setEditedFormData({
      ...editedFormData,
      permissions: {...editedFormData.permissions, apply_on_children: editedApplyOnChildren},
    });
  };

  const saveCategory = useCallback(async () => {
    if (categoryData === null || editedFormData === null) {
      return;
    }

    const response = await saveEditCategoryForm(categoryData.category.id, editedFormData);

    if (response.success) {
      notify(NotificationLevel.SUCCESS, translate('pim_enrich.entity.category.content.edit.success'));
      setOriginalFormData(response.form);
    } else {
      notify(NotificationLevel.ERROR, translate('pim_enrich.entity.category.content.edit.fail'));
      const refreshedToken = {...editedFormData._token, value: response.form._token.value};
      setEditedFormData({...editedFormData, _token: refreshedToken});
    }
  }, [categoryData, editedFormData]);

  useEffect(() => {
    loadCategory();
  }, []);

  useEffect(() => {
    if (categoryData === null) {
      return;
    }

    setOriginalFormData(categoryData.form);
  }, [categoryData]);

  useEffect(() => {
    if (originalFormData === null) {
      return;
    }

    // Because the value of "apply_on_children" is always returned as "1" by the backend, it should only be defined at the first load
    if (originalFormData.permissions && editedFormData !== null && editedFormData.permissions) {
      setEditedFormData({
        ...originalFormData,
        permissions: {...originalFormData.permissions, apply_on_children: editedFormData.permissions.apply_on_children},
      });
    } else {
      setEditedFormData({...originalFormData});
    }
  }, [originalFormData]);

  useEffect(() => {
    if (editedFormData !== null) {
      setThereAreUnsavedChanges(haveLabelsBeenChanged() || havePermissionsBeenChanged());
    }
  }, [editedFormData]);

  useEffect(() => {
    setCanLeavePage(!thereAreUnsavedChanges);
  }, [thereAreUnsavedChanges]);

  return {
    categoryLoadingStatus,
    category: categoryData ? categoryData.category : null,
    formData: editedFormData,
    onChangeCategoryLabel,
    onChangePermissions,
    onChangeApplyPermissionsOnChilren,
    thereAreUnsavedChanges,
    saveCategory,
  };
};

export {useEditCategoryForm};
