<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;
use Doctrine\Common\Persistence\ObjectManager;
use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use App\Entity\Avions;
use App\Repository\AvionsRepository;
use App\Entity\Vol;
use App\Entity\Typevol;
use App\Form\VolType;
use App\Form\VolEditType;
use App\Entity\SonataUserUser;
use App\Entity\OperationComptable;
use App\Form\OperationComptableType;
use App\Form\OperationComptableEditType;

class VolController extends Controller
{
    #[Route('/vol', name: 'app_vol')]
    public function index(): Response
    {
        return $this->render('vol/index.html.twig', [
            'controller_name' => 'VolController', array('user'=>$reservataire)
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
                $form->getData()->setFacture($form->getData()->getMontantFacture());

                //On renseigne tous les attributs OperationComptable pr le compte financier du Pilote 
                $operation = new OperationComptable();
                $operation->setCompteId($form->getData()->getUser()->getId());
                $operation->setUser($form->getData()->getUser());
                $operation->setOperMontant($form->getData()->getMontantFacture());
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

}
