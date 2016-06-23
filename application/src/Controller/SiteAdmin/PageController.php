<?php
namespace Omeka\Controller\SiteAdmin;

use Omeka\Form\ConfirmForm;
use Omeka\Form\SitePageForm;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class PageController extends AbstractActionController
{
    public function editAction()
    {
        $site = $this->currentSite();
        $page = $this->api()->read('site_pages', [
            'slug' => $this->params('page-slug'),
            'site' => $site->id(),
        ])->getContent();

        $form = $this->getForm(SitePageForm::class);
        $form->setData($page->jsonSerialize());

        if ($this->getRequest()->isPost()) {
            $post = $this->params()->fromPost();
            $form->setData($post);
            if ($form->isValid()) {
                $response = $this->api()->update('site_pages', $page->id(), $post);
                if ($response->isError()) {
                    $form->setMessages($response->getErrors());
                } else {
                    $this->messenger()->addSuccess('Page updated.');
                    // Explicitly re-read the site URL instead of using
                    // refresh() so we catch updates to the slug
                    return $this->redirect()->toUrl($page->url());
                }
            } else {
                $this->messenger()->addError('There was an error during validation');
            }
        }

        $view = new ViewModel;
        $this->layout()->setVariable('site', $site);
        $view->setVariable('site', $site);
        $view->setVariable('page', $page);
        $view->setVariable('form', $form);
        return $view;
    }

    public function indexAction()
    {
        $site = $this->currentSite();

        $indents = [];
        $iterate = function ($linksIn, $depth = 0) use (&$iterate, &$indents)
        {
            foreach ($linksIn as $key => $data) {
                if ('page' === $data['type']) {
                    $indents[$data['data']['id']] = $depth;
                }
                if (isset($data['links'])) {
                    $iterate($data['links'], $depth + 1);
                }
            }
        };
        $iterate($site->navigation());

        $view = new ViewModel;
        $this->layout()->setVariable('site', $site);
        $view->setVariable('site', $site);
        $view->setVariable('indents', $indents);
        $view->setVariable('pages', array_merge($site->linkedPages(), $site->notlinkedPages()));
        return $view;
    }

    public function deleteConfirmAction()
    {
        $site = $this->currentSite();
        $page = $this->api()->read('site_pages', [
            'slug' => $this->params('page-slug'),
            'site' => $site->id(),
        ])->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('partialPath', 'omeka/site-admin/page/show-details');
        $view->setVariable('resourceLabel', 'page');
        $view->setVariable('resource', $page);
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api()->delete('site_pages', ['slug' => $this->params('page-slug')]);
                if ($response->isError()) {
                    $this->messenger()->addError('Page could not be deleted');
                } else {
                    $this->messenger()->addSuccess('Page successfully deleted');
                }
            } else {
                $this->messenger()->addError('Page could not be deleted');
            }
        }
        return $this->redirect()->toRoute(
            'admin/site/page',
            ['action' => 'index'],
            true
        );
    }

    public function blockAction()
    {
        $content = $this->viewHelpers()->get('blockLayout')->form(
            $this->params()->fromPost('layout'),
            $this->currentSite()
        );

        $response = $this->getResponse();
        $response->setContent($content);
        return $response;
    }

    public function attachmentItemOptionsAction()
    {
        $attachedItem = null;
        $attachedMedia = null;

        $itemId = $this->params()->fromPost('itemId');
        if ($itemId) {
            $attachedItem = $this->api()->read('items', $itemId)->getContent();
        }
        $mediaId = $this->params()->fromPost('mediaId');
        if ($mediaId) {
            $attachedMedia = $this->api()->read('media', $mediaId)->getContent();
        }

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('attachedItem', $attachedItem);
        $view->setVariable('attachedMedia', $attachedMedia);
        $view->setVariable('site', $this->currentSite());
        return $view;
    }
}
