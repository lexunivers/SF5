<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormRenderer;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Doctrine\Common\Persistence\ObjectManager;
use Sonata\AdminBundle\Controller\CRUDController as Controller;

use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Bundle\PaginatorBundle\Definition;
use App\Entity\SonataUserUser;
use App\Entity\OperationComptable;
use App\Form\OperationComptableType;
use App\Form\OperationComptableEditType;
use App\Repository\OperationComptableRepository;
use Sonata\AdminBundle\Route\RouteCollection;

use Symfony\Component\HttpFoundation\JsonResponse;


use Symfony\Component\HttpFoundation\RequestStack;


class OperationComptableController extends Controller
{
    /**
     * @Route("/operation_comptable", name="operationcomptable")
     */
    public function index()
    {
        return $this->render('operation_comptable/index.html.twig', [
            'controller_name' => 'OperationComptableController',
        ]);
    }

    /**
     * This method can be overloaded in your custom CRUD controller.
     * It's called from createAction.
     *
     * @phpstan-param T $object
     */
    protected function preCreate(Request $request, object $object): ?Response
    {
        $newObject = $this->admin->getNewInstance();
        $this->admin->setSubject($newObject);
        $form = $this->admin->getForm();
        $form->setData($newObject);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $isFormValid = $form->isValid();
            // persist if the form was valid and if in preview mode the preview was approved
            if ($isFormValid && (!$this->isInPreviewMode($request) || $this->isPreviewApproved($request))) {
                /** @phpstan-var T $submittedObject */
                $submittedObject = $form->getData();				

                // on renseigne l'attribut facture de l'entity Vol pour enregistrement en BDD
               // $form->getData()->setFacture($form->getData()->getMontantFacture());

                //On renseigne tous les attributs OperationComptable  
                $operation = new OperationComptable();
                $operation->setCompteId($form->getData()->getUser()->getId());
                $operation->setUser($form->getData()->getUser());
                $operation->setOperMontant($form->getData()->getOperMontant());
                $operation ->setOperSensMt(0);
                $operation->setLibelle($form->getData()->getLibelle());

                $this->admin->setSubject($submittedObject);
                try {
                    $newObject = $this->admin->create($submittedObject);					
                 
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($operation);
                    $em->flush();

                    if ($this->isXmlHttpRequest($request)) {
                        return $this->handleXmlHttpRequestSuccessResponse($request, $newObject);
                    }
                    $this->addFlash(
                        'sonata_flash_success',
                        $this->trans(
                            'flash_create_success',
                            ['%name%' => $this->escapeHtml($this->admin->toString($newObject))],
                            'SonataAdminBundle'
                        )
                    );
                    // redirect to edit mode
                    return $this->redirectTo($request, $newObject);
                } catch (ModelManagerException $e) {
                    // NEXT_MAJOR: Remove this catch.
                    $this->handleModelManagerException($e);
                    $isFormValid = false;
                } catch (ModelManagerThrowable $e) {
                    $errorMessage = $this->handleModelManagerThrowable($e);
                    $isFormValid = false;
                }
            }
        }
		return null;		
	}  



    
    public function AjouterEcritureAction(OperationComptable $Operation = null, Request $request, ObjectManager $manager = null)
    {
        $Operation = new OperationComptable();
        $operation->setCompteId($this->container->get('security.token_storage')->getToken()->getUser()->getId());
        
        var_dump($operation->setCompteId($this->container->get('security.token_storage')->getToken()->getUser()->getId()) );
//exit;
        $form    = $this->createForm(OperationComptableType::class, $Operation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            $em->persist($Operation);
            $em->flush();

            $request->getSession()->getFlashBag()->add('notice', 'Votre Compte a bien été mis à jour.');

            // $this->addFlash('info', 'Profile updated');
            // $this->addFlash('warning', 'Profile issued');

            return $this->redirect($this->generateUrl('sky_compte_ajouter', array('id' => $Operation->getId())));
        }
                    
        return $this->render('/MonCompte/AjoutEcritures.html.twig', array(
            'formMonCompte'    => $form->createView(),
            'editMode' => $Operation->getId() !== null                
        ));
    }
}
